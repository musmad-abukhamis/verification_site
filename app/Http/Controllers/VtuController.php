<?php

namespace App\Http\Controllers;

use App\Models\Plan;
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

    private function walletPayload($user): array
    {
        $balance = (float) $user->balance;

        return [
            'balance' => $balance,
            'bonus_balance' => 0.0,
            'total_balance' => $balance,
        ];
    }

    /**
     * Show airtime purchase page
     */
    public function airtime()
    {
        return Inertia::render('Vtu/Airtime', [
            'wallet' => $this->walletPayload(Auth::user()),
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
        if (! $phoneCheck['valid']) {
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
        $dataPlans = Plan::active()
            ->orderBy('network')
            ->orderBy('price')
            ->get()
            ->groupBy('network');

        return Inertia::render('Vtu/Data', [
            'wallet' => $this->walletPayload(Auth::user()),
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
            'plan_id' => 'required|exists:Plan,id',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        // Verify phone number matches network
        $phoneCheck = $this->vtuService->verifyPhoneNumber($validated['phone_number']);
        if (! $phoneCheck['valid']) {
            return back()->withErrors(['phone_number' => 'Invalid phone number']);
        }

        if ($phoneCheck['network'] !== $validated['network']) {
            return back()->withErrors(['phone_number' => 'Phone number does not match selected network']);
        }

        $result = $this->vtuService->purchaseData(
            Auth::id(),
            $validated['network'],
            $validated['phone_number'],
            (string) $plan->id,
            (float) $plan->priceForUser(Auth::user()),
            null,
            $plan->name
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
        return Inertia::render('BuyData/Index', [
            'wallet' => $this->walletPayload(Auth::user()),
            'networks' => [
                ['value' => 'mtn', 'label' => 'MTN'],
                ['value' => 'glo', 'label' => 'Glo'],
                ['value' => 'airtel', 'label' => 'Airtel'],
                ['value' => '9mobile', 'label' => '9mobile'],
            ],
            'user' => [
                'id' => Auth::id(),
                'role' => Auth::user()->role?->value ?? 'USER',
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
            'planId' => 'required|exists:Plan,id',
            'planName' => 'required|string',
            'planPrice' => 'required|numeric|min:0',
            'phoneNumber' => 'required|string|min:10|max:11',
        ]);

        $plan = Plan::findOrFail($validated['planId']);

        // Verify phone number
        $phoneCheck = $this->vtuService->verifyPhoneNumber($validated['phoneNumber']);
        if (! $phoneCheck['valid']) {
            return back()->withErrors(['phoneNumber' => 'Invalid phone number']);
        }

        $result = $this->vtuService->purchaseData(
            Auth::id(),
            $validated['network'],
            $validated['phoneNumber'],
            (string) $plan->id,
            (float) $plan->priceForUser(Auth::user()),
            null,
            $plan->name
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
        $types = Plan::active()
            ->byNetwork($network)
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->filter()
            ->values();

        return response()->json([
            'types' => $types,
        ]);
    }

    /**
     * Get data plans for network and type (API)
     */
    public function getDataPlans($network, $type)
    {
        $user = Auth::user();

        $plans = Plan::active()
            ->byNetwork($network)
            ->byType($type)
            ->orderBy('price')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->priceForUser($user),
                'validity' => $plan->validity,
            ]);

        return response()->json([
            'plans' => $plans,
        ]);
    }
}
