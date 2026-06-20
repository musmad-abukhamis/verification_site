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

        if ($user->balance < $validatedData['planPrice']) {
            return Inertia::render('BuyData/Index', ['error' => 'Insufficient Balance! Please fund your wallet to continue the transaction.']);
        }

        // 7. Prepare API configuration based on active vendor
        $apiConfig = $this->prepareApiConfig($vendorNumber, $vendorApi, $validatedData, $reference, $planIds, $networkProviders);

        // 8. Make API call to vendor
        $apiResult = $this->callVendorApi($apiConfig['url'], $apiConfig['token'], $apiConfig['payload']);

        // 9. Process transaction
        if ($this->isSuccessfulResponse($apiResult)) {
            $oldBalance = (float) $user->balance;
            $user->debit((float) $validatedData['planPrice'], false, ['fundingtype' => 'data']);

            Transaction::create([
                'id' => $reference,
                'status' => 'success',
                'network' => $validatedData['network'],
                'type' => 'data',
                'userId' => $user->id,
                'name' => $validatedData['planName'],
                'price' => (int) round($validatedData['planPrice']),
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
            'name' => $validatedData['planName'],
            'price' => (int) round($validatedData['planPrice']),
            'phone' => $validatedData['phoneNumber'],
            'oldbal' => (float) $user->balance,
            'newbal' => (float) $user->balance,
            'response' => 'Vendor-'.$vendorNumber.' - '.json_encode($apiResult),
        ]);

        // Switch to alternative vendor for the next attempt
        $this->switchToAlternativeVendor($validatedData['network'], $validatedData['type'], $vendorNumber);

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
     * Prepare API configuration for the vendor call.
     */
    protected function prepareApiConfig(int $vendorNumber, $vendorApi, $validatedData, $reference, $planIds, $networkProviders): array
    {
        $vendorUrl = $vendorApi->{'vendor'.$vendorNumber.'url'};
        $vendorKey = $vendorApi->{'vendor'.$vendorNumber.'key'};

        $vendorPlanId = $planIds['vendor'.$vendorNumber] ?? $validatedData['planId'];
        $vendorNetwork = $networkProviders['vendor'.$vendorNumber] ?? $validatedData['network'];

        return [
            'url' => $vendorUrl,
            'token' => $vendorKey,
            'payload' => [
                'request_id' => $reference,
                'serviceID' => $this->getServiceId($vendorNetwork, 'data'),
                'billersCode' => $validatedData['phoneNumber'],
                'variation_code' => $vendorPlanId,
                'phone' => $validatedData['phoneNumber'],
            ],
        ];
    }

    /**
     * Call vendor API
     */
    protected function callVendorApi($url, $token, $payload)
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            return $response->json();
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'API call failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Check if API response is successful
     */
    protected function isSuccessfulResponse($apiResult)
    {
        return isset($apiResult['code']) && $apiResult['code'] === '000';
    }

    /**
     * Advance to the next vendor (1-5, wrapping) for the given service type.
     */
    protected function switchToAlternativeVendor(string $network, string $type, int $currentVendor): void
    {
        $selection = VendorSelection::firstOrCreate(['id' => strtoupper($network)]);
        $column = $this->vendorSelectionColumn($type);

        $next = $currentVendor >= 5 ? 1 : $currentVendor + 1;
        $selection->update([$column => (string) $next]);
    }

    /**
     * Map network to service ID for vendor API
     */
    protected function getServiceId($network, $service)
    {
        $mapping = [
            'mtn' => ['data' => 'mtn-data'],
            'glo' => ['data' => 'glo-data'],
            'airtel' => ['data' => 'airtel-data'],
            '9mobile' => ['data' => 'etisalat-data'],
        ];

        return $mapping[strtolower($network)][$service] ?? $network;
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
