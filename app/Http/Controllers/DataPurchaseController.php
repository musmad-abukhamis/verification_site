<?php

namespace App\Http\Controllers;

use App\Models\Network;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\VendorApi;
use App\Models\VendorSelection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class DataPurchaseController extends Controller
{
    /**
     * Handle data bundle purchase with multi-vendor routing/failover.
     */
    public function buyData(Request $request)
    {
        // 1. Validate incoming request using Laravel validation
        $validator = Validator::make($request->all(), [
            'network' => 'required|string',
            'type' => 'required|string',
            'planName' => 'required|string',
            'planPrice' => 'required|numeric',
            'planId' => 'required|integer',
            'phoneNumber' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Inertia::render('BuyData/Index', ['error' => 'Invalid fields']);
        }

        $validatedData = $validator->validated();

        // 2. Fetch vendor API configuration from database
        $vendorApi = VendorApi::first();
        if (! $vendorApi) {
            return Inertia::render('BuyData/Index', ['error' => 'No vendor data found']);
        }

        // 3. Generate transaction reference
        $reference = 'Data_'.time();

        // 4. Fetch vendor-specific plans and networks
        $planIds = $this->getVendorPlans($validatedData['planId']);
        $networkProviders = $this->getVendorNetwork($validatedData['network']);

        // 5. Determine active vendor (1-5) for this network + service type
        $vendorNumber = $this->getActiveVendorNumber($validatedData['network'], $validatedData['type']);

        // 6. Get authenticated user and check balance
        $user = Auth::user();
        if (! $user) {
            return Inertia::render('BuyData/Index', ['error' => 'User not found']);
        }

        // Never trust the client-supplied price: resolve the authoritative,
        // role-adjusted price from the plan itself.
        $plan = Plan::find($validatedData['planId']);
        if (! $plan) {
            return Inertia::render('BuyData/Index', ['error' => 'Selected plan not found']);
        }
        $price = (float) $plan->priceForUser($user);

        if ((float) $user->balance < $price) {
            return Inertia::render('BuyData/Index', ['error' => 'Insufficient Balance! Please fund your wallet to continue the transaction.']);
        }

        // 7. Prepare API configuration based on active vendor
        $apiConfig = $this->prepareApiConfig($vendorNumber, $vendorApi, $validatedData, $reference, $planIds, $networkProviders);

        // 8. Make API call to vendor
        $apiResult = $this->callVendorApi($apiConfig['url'], $apiConfig['token'], $apiConfig['payload']);

        // 9. Process transaction
        if ($this->isSuccessfulResponse($apiResult)) {
            $oldBalance = (float) $user->balance;
            $user->debit($price, false, ['fundingtype' => 'data']);

            Transaction::create([
                'id' => $reference,
                'status' => 'success',
                'network' => $validatedData['network'],
                'type' => 'data',
                'userId' => $user->id,
                'name' => $plan->name,
                'price' => (int) round($price),
                'phone' => $validatedData['phoneNumber'],
                'oldbal' => $oldBalance,
                'newbal' => (float) $user->balance,
                'response' => 'Vendor-'.$vendorNumber.' - '.json_encode($apiResult),
            ]);

            return Inertia::location(route('buy-data'));
        }

        // Failed transaction record (balance unchanged)
        Transaction::create([
            'id' => $reference,
            'status' => 'fail',
            'network' => $validatedData['network'],
            'type' => 'data',
            'userId' => $user->id,
            'name' => $plan->name,
            'price' => (int) round($price),
            'phone' => $validatedData['phoneNumber'],
            'oldbal' => (float) $user->balance,
            'newbal' => (float) $user->balance,
            'response' => 'Vendor-'.$vendorNumber.' - '.json_encode($apiResult),
        ]);

        return Inertia::render('BuyData/Index', [
            'error' => $apiResult['message'] ?? 'Failed to process the transaction',
        ]);
    }

    /**
     * Per-vendor plan codes now live on the Plan row (vendorPlan1..5).
     */
    protected function getVendorPlans($planId): ?array
    {
        $plan = Plan::find($planId);
        if (! $plan) {
            return null;
        }

        return [
            'vendor1' => $plan->vendorPlan1,
            'vendor2' => $plan->vendorPlan2,
            'vendor3' => $plan->vendorPlan3,
            'vendor4' => $plan->vendorPlan4,
            'vendor5' => $plan->vendorPlan5,
        ];
    }

    /**
     * Per-vendor network codes live on the `networks` row keyed by network name.
     */
    protected function getVendorNetwork($network): ?array
    {
        $vendorNetwork = Network::find($network);
        if (! $vendorNetwork) {
            return null;
        }

        return [
            'vendor1' => $vendorNetwork->vendor1network,
            'vendor2' => $vendorNetwork->vendor2network,
            'vendor3' => $vendorNetwork->vendor3network,
            'vendor4' => $vendorNetwork->vendor4network,
            'vendor5' => $vendorNetwork->vendor5network,
        ];
    }

    /**
     * Map a service type to its `vendorselection` column.
     */
    protected function vendorSelectionColumn(string $type): string
    {
        return match (strtoupper(str_replace(' ', '_', $type))) {
            'SME' => 'SME',
            'SME2' => 'SME2',
            'CORPORATE_GIFTING', 'GIFTING' => 'CORPORATE_GIFTING',
            'CORPORATE_GIFTING2' => 'CORPORATE_GIFTING2',
            'DATASHARE', 'CORPORATE' => 'DATASHARE',
            default => 'SME',
        };
    }

    /**
     * The currently active vendor number (1-5) for a network + service type.
     * Vendor selections are stored one row per network (id = network name).
     */
    protected function getActiveVendorNumber(string $network, string $type): int
    {
        $selection = VendorSelection::firstOrCreate(['id' => strtoupper($network)]);
        $column = $this->vendorSelectionColumn($type);

        return max(1, min(5, (int) ($selection->{$column} ?? 1)));
    }

    /**
     * Prepare the API call for the active vendor.
     *
     * Faithful to nimcweb's Buy-data-action: vendors 1 & 5 take a
     * {network, phone, bypass, data_plan, request-id} body; vendors 2-4 take a
     * {network, mobile_number, plan, Ported_number} body. Vendor 5 authenticates
     * with a freshly-fetched Quicklysim access token instead of its stored key.
     */
    protected function prepareApiConfig(int $vendorNumber, $vendorApi, $validatedData, $reference, $planIds, $networkProviders): array
    {
        $vendorUrl = $vendorApi->{'vendor'.$vendorNumber.'url'};
        $vendorKey = $vendorApi->{'vendor'.$vendorNumber.'key'};

        $vendorPlanId = $planIds['vendor'.$vendorNumber] ?? $validatedData['planId'];
        $vendorNetwork = $networkProviders['vendor'.$vendorNumber] ?? $validatedData['network'];
        $phone = $validatedData['phoneNumber'];

        $payload = match ($vendorNumber) {
            2, 3, 4 => [
                'network' => $vendorNetwork,
                'mobile_number' => $phone,
                'plan' => $vendorPlanId,
                'Ported_number' => true,
            ],
            // Vendors 1 and 5 share the same body shape.
            default => [
                'network' => $vendorNetwork,
                'phone' => $phone,
                'bypass' => true,
                'data_plan' => $vendorPlanId,
                'request-id' => $reference,
            ],
        };

        $token = $vendorNumber === 5 ? ($this->getQuicklysimToken() ?? $vendorKey) : $vendorKey;

        return [
            'url' => $vendorUrl,
            'token' => $token,
            'payload' => $payload,
        ];
    }

    /**
     * Call the vendor API. Auth scheme is "Token <key>" (nimcweb convention).
     */
    protected function callVendorApi($url, $token, $payload)
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Token '.$token,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'API call failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Success = the vendor returned status/Status of "success"/"successful".
     */
    protected function isSuccessfulResponse($apiResult): bool
    {
        $status = strtolower((string) ($apiResult['status'] ?? $apiResult['Status'] ?? ''));

        return in_array($status, ['success', 'successful'], true);
    }

    /**
     * Fetch a Quicklysim access token (vendor 5) via HTTP Basic auth.
     * Returns null when credentials are unconfigured or the call fails.
     */
    protected function getQuicklysimToken(): ?string
    {
        $username = config('services.quicklysim.username');
        $password = config('services.quicklysim.password');
        $url = config('services.quicklysim.base_url').'/user';

        if (! $username || ! $password) {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->withBasicAuth($username, $password)
                ->post($url);

            return $response->successful() ? ($response->json('AccessToken') ?: null) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get data for the buy-data page
     */
    public function index()
    {
        $user = Auth::user();
        $balance = (float) $user->balance;

        return Inertia::render('BuyData/Index', [
            'wallet' => [
                'balance' => $balance,
                'bonus_balance' => 0.0,
                'total_balance' => $balance,
            ],
            'networks' => [
                ['value' => 'mtn', 'label' => 'MTN'],
                ['value' => 'glo', 'label' => 'Glo'],
                ['value' => 'airtel', 'label' => 'Airtel'],
                ['value' => '9mobile', 'label' => '9mobile'],
            ],
            'user' => [
                'id' => $user->id,
                'role' => $user->role?->value ?? 'USER',
            ],
        ]);
    }
}
