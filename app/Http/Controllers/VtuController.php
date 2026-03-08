<?php

namespace App\Http\Controllers;

use App\Models\DataPlan;
use App\Models\Wallet;
use App\Services\VtuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class VtuController extends Controller
{
    protected VtuService $vtuService;

    public function __construct(VtuService $vtuService)
    {
        $this->vtuService = $vtuService;
    }

    /**
     * Show airtime purchase page
     */
    public function airtime()
    {
        $userId = Auth::id();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        return Inertia::render('Vtu/Airtime', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => $wallet->total_balance,
            ],
            'networks' => [
                ['value' => 'mtn', 'label' => 'MTN', 'color' => 'yellow'],
                ['value' => 'glo', 'label' => 'Glo', 'color' => 'green'],
                ['value' => 'airtel', 'label' => 'Airtel', 'color' => 'red'],
                ['value' => '9mobile', 'label' => '9mobile', 'color' => 'teal'],
            ],
        ]);
    }

    /**
     * Purchase airtime
     */
    public function purchaseAirtime(Request $request)
    {
        $validated = $request->validate([
            'network' => 'required|in:mtn,glo,airtel,9mobile',
            'phone_number' => 'required|string|min:10|max:11',
            'amount' => 'required|numeric|min:50|max:50000',
        ]);

        // Verify phone number
        $phoneCheck = $this->vtuService->verifyPhoneNumber($validated['phone_number']);
        if (!$phoneCheck['valid']) {
            return back()->withErrors(['phone_number' => 'Invalid phone number']);
        }

        $result = $this->vtuService->purchaseAirtime(
            Auth::id(),
            $validated['network'],
            $validated['phone_number'],
            (float) $validated['amount']
        );

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return back()->withErrors(['error' => $result['message']]);
    }

    /**
     * Show data purchase page
     */
    public function data()
    {
        $userId = Auth::id();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        $dataPlans = DataPlan::active()
            ->orderBy('network')
            ->orderBy('price')
            ->get()
            ->groupBy('network');

        return Inertia::render('Vtu/Data', [
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
            'data_plans' => $dataPlans,
        ]);
    }

    /**
     * Purchase data bundle
     */
    public function purchaseData(Request $request)
    {
        $validated = $request->validate([
            'network' => 'required|in:mtn,glo,airtel,9mobile',
            'phone_number' => 'required|string|min:10|max:11',
            'plan_id' => 'required|exists:data_plans,id',
        ]);

        $plan = DataPlan::findOrFail($validated['plan_id']);

        // Verify phone number matches network
        $phoneCheck = $this->vtuService->verifyPhoneNumber($validated['phone_number']);
        if (!$phoneCheck['valid']) {
            return back()->withErrors(['phone_number' => 'Invalid phone number']);
        }

        if ($phoneCheck['network'] !== $validated['network']) {
            return back()->withErrors(['phone_number' => 'Phone number does not match selected network']);
        }

        $result = $this->vtuService->purchaseData(
            Auth::id(),
            $validated['network'],
            $validated['phone_number'],
            $plan->id, // or plan code from your provider
            (float) $plan->price
        );

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return back()->withErrors(['error' => $result['message']]);
    }

    /**
     * Verify phone number (AJAX)
     */
    public function verifyPhone(Request $request)
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|min:10|max:11',
        ]);

        $result = $this->vtuService->verifyPhoneNumber($validated['phone_number']);

        return response()->json($result);
    }

    /**
     * Show buy data page
     */
    public function buyData()
    {
        $userId = Auth::id();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'bonus_balance' => 0]
        );

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
                'id' => Auth::id(),
                'role' => Auth::user()->role ?? 'USER',
            ],
        ]);
    }

    /**
     * Purchase data from buy data page
     */
    public function purchaseBuyData(Request $request)
    {
        $validated = $request->validate([
            'network' => 'required|in:mtn,glo,airtel,9mobile',
            'type' => 'required|string',
            'planId' => 'required|exists:data_plans,id',
            'planName' => 'required|string',
            'planPrice' => 'required|numeric|min:0',
            'phoneNumber' => 'required|string|min:10|max:11',
        ]);

        $plan = DataPlan::findOrFail($validated['planId']);

        // Verify phone number
        $phoneCheck = $this->vtuService->verifyPhoneNumber($validated['phoneNumber']);
        if (!$phoneCheck['valid']) {
            return back()->withErrors(['phoneNumber' => 'Invalid phone number']);
        }

        $result = $this->vtuService->purchaseData(
            Auth::id(),
            $validated['network'],
            $validated['phoneNumber'],
            $plan->id,
            (float) $validated['planPrice']
        );

        if ($result['success']) {
            return redirect()->route('buy-data')->with('success', $result['message']);
        }

        return back()->withErrors(['error' => $result['message']]);
    }

    /**
     * Get data types for network (API)
     */
    public function getDataTypes($network)
    {
        // In a real implementation, this would fetch from your VTU provider
        // For now, we'll return static data
        $types = [
            'mtn' => ['SME', 'GIFTING', 'CORPORATE'],
            'glo' => ['NORMAL', 'GIFTING'],
            'airtel' => ['NORMAL', 'GIFTING', 'CORPORATE'],
            '9mobile' => ['NORMAL', 'GIFTING']
        ];

        return response()->json([
            'types' => $types[strtolower($network)] ?? []
        ]);
    }

    /**
     * Get data plans for network and type (API)
     */
    public function getDataPlans($network, $type)
    {
        $plans = DataPlan::active()
            ->byNetwork($network)
            ->where('plan_type', $type)
            ->orderBy('price')
            ->get()
            ->map(function ($plan) {
                $user = Auth::user();
                $price = $plan->price;
                
                // Role-based pricing
                if ($user->role === 'AGENT') {
                    $price = $plan->agent_price ?? $plan->price;
                } elseif ($user->role === 'API') {
                    $price = $plan->api_price ?? $plan->price;
                }
                
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $price,
                    'validity_days' => $plan->validity_days,
                    'data_volume' => $plan->data_volume,
                ];
            });

        return response()->json([
            'plans' => $plans
        ]);
    }
}