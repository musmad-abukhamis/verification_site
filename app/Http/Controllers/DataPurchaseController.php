<?php

namespace App\Http\Controllers;

use App\Models\ActiveVendor;
use App\Models\DataPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendorApi;
use App\Models\VendorNetwork;
use App\Models\VendorPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class DataPurchaseController extends Controller
{
    /**
     * Handle data bundle purchase
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
            'phoneNumber' => 'required|string'
        ]);

        if ($validator->fails()) {
            return Inertia::render('BuyData/Index', ['error' => 'Invalid fields']);
        }

        $validatedData = $validator->validated();

        // 2. Fetch vendor API configuration from database
        $vendorApi = VendorApi::first();
        if (!$vendorApi) {
            return Inertia::render('BuyData/Index', ['error' => 'No vendor data found']);
        }

        // 3. Generate transaction reference
        $reference = 'Data_' . time();

        // 4. Fetch vendor-specific plans and networks
        $planIds = $this->getVendorPlans($validatedData['planId']);
        $networkProviders = $this->getVendorNetwork($validatedData['network']);

        // 5. Determine active vendor for this network and type
        $activeVendor = $this->getActiveVendor($validatedData['network'], $validatedData['type']);

        // 6. Get authenticated user and check balance
        $user = Auth::user();
        if (!$user) {
            return Inertia::render('BuyData/Index', ['error' => 'User not found']);
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            return Inertia::render('BuyData/Index', ['error' => 'Wallet not found']);
        }

        if ($wallet->balance < $validatedData['planPrice']) {
            return Inertia::render('BuyData/Index', ['error' => 'Insufficient Balance! Please fund your wallet to continue the transaction.']);
        }

        // 7. Prepare API configuration based on active vendor
        $apiConfig = $this->prepareApiConfig($activeVendor, $vendorApi, $validatedData, $reference, $planIds, $networkProviders);

        // 8. Make API call to vendor
        $apiResult = $this->callVendorApi($apiConfig['url'], $apiConfig['token'], $apiConfig['payload']);

        // 9. Calculate new balance and process transaction
        if ($this->isSuccessfulResponse($apiResult)) {
            // Debit user wallet and create successful transaction
            $wallet->debit($validatedData['planPrice']);
            
            Transaction::create([
                'id' => $reference,
                'status' => 'success',
                'network' => $validatedData['network'],
                'type' => 'data',
                'userId' => $user->id,
                'name' => $validatedData['planName'],
                'price' => $validatedData['planPrice'],
                'phone' => $validatedData['phoneNumber'],
                'oldbal' => $wallet->balance + $validatedData['planPrice'],
                'newbal' => $wallet->balance,
                'response' => 'Vendor-' . $activeVendor->vendorApi . ' - ' . json_encode($apiResult)
            ]);

            return Inertia::location(route('buy-data'));
        } else {
            // Create failed transaction record
            Transaction::create([
                'id' => $reference,
                'status' => 'fail',
                'network' => $validatedData['network'],
                'type' => 'data',
                'userId' => $user->id,
                'name' => $validatedData['planName'],
                'price' => $validatedData['planPrice'],
                'phone' => $validatedData['phoneNumber'],
                'oldbal' => $wallet->balance,
                'newbal' => $wallet->balance, // Balance unchanged on failure
                'response' => 'Vendor-' . $activeVendor->vendorApi . ' - ' . json_encode($apiResult)
            ]);

            // Switch to alternative vendor
            $this->switchToAlternativeVendor($activeVendor, $apiResult);

            return Inertia::render('BuyData/Index', [
                'error' => $apiResult['message'] ?? 'Failed to process the transaction'
            ]);
        }
    }

    /**
     * Get vendor plans for specific data plan
     */
    protected function getVendorPlans($planId)
    {
        $vendorPlan = VendorPlan::where('plan_id', $planId)->first();
        if (!$vendorPlan) {
            return null;
        }

        return [
            'vendor1' => $vendorPlan->vendor_plan1,
            'vendor2' => $vendorPlan->vendor_plan2,
            'vendor3' => $vendorPlan->vendor_plan3,
            'vendor4' => $vendorPlan->vendor_plan4,
            'vendor5' => $vendorPlan->vendor_plan5,
        ];
    }

    /**
     * Get vendor network mappings
     */
    protected function getVendorNetwork($network)
    {
        $vendorNetwork = VendorNetwork::where('network', $network)->first();
        if (!$vendorNetwork) {
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
     * Get active vendor for network and type
     */
    protected function getActiveVendor($network, $type)
    {
        return ActiveVendor::where('network', $network)
            ->where('type', $type)
            ->first();
    }

    /**
     * Prepare API configuration for vendor call
     */
    protected function prepareApiConfig($activeVendor, $vendorApi, $validatedData, $reference, $planIds, $networkProviders)
    {
        if (!$activeVendor) {
            throw new \Exception('No active vendor configured');
        }

        $vendorNumber = $activeVendor->vendor_number;
        $vendorUrl = $vendorApi->{'vendor' . $vendorNumber . 'url'};
        $vendorKey = $vendorApi->{'vendor' . $vendorNumber . 'key'};
        
        $vendorPlanId = $planIds['vendor' . $vendorNumber] ?? $validatedData['planId'];
        $vendorNetwork = $networkProviders['vendor' . $vendorNumber] ?? $validatedData['network'];

        return [
            'url' => $vendorUrl,
            'token' => $vendorKey,
            'payload' => [
                'request_id' => $reference,
                'serviceID' => $this->getServiceId($vendorNetwork, 'data'),
                'billersCode' => $validatedData['phoneNumber'],
                'variation_code' => $vendorPlanId,
                'phone' => $validatedData['phoneNumber'],
            ]
        ];
    }

    /**
     * Call vendor API
     */
    protected function callVendorApi($url, $token, $payload)
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            return $response->json();
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'API call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if API response is successful
     */
    protected function isSuccessfulResponse($apiResult)
    {
        // Customize based on your vendor's success response format
        return isset($apiResult['code']) && $apiResult['code'] === '000';
    }

    /**
     * Switch to alternative vendor on failure
     */
    protected function switchToAlternativeVendor($activeVendor, $apiResult)
    {
        // Logic to switch to next available vendor
        // This is a simplified version - you might want to implement more sophisticated vendor switching
        $nextVendorNumber = $activeVendor->vendor_number + 1;
        if ($nextVendorNumber <= 5) {
            $activeVendor->update(['vendor_number' => $nextVendorNumber]);
        }
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
        $wallet = $user->wallet;
        
        return Inertia::render('BuyData/Index', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => $wallet->total_balance,
            ],
            'networks' => [
                ['value' => 'mtn', 'label' => 'MTN'],
                ['value' => 'glo', 'label' => 'Glo'],
                ['value' => 'airtel', 'label' => 'Airtel'],
                ['value' => '9mobile', 'label' => '9mobile'],
            ],
            'user' => [
                'id' => $user->id,
                'role' => $user->role ?? 'USER',
            ],
        ]);
    }
}