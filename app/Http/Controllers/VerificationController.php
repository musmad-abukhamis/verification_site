<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class VerificationController extends Controller
{
    protected VerificationService $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Show NIN verification page
     */
    public function nin()
    {
        $userId = Auth::id();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        $ninPrice = config('services.verification.nin_price', 100);

        return Inertia::render('Verification/Nin', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => $wallet->total_balance,
            ],
            'price' => $ninPrice,
            'verificationMethods' => config('services.verification.nin_methods', [
                'nin' => ['active' => true, 'label' => 'By NIN Number'],
                'phone' => ['active' => true, 'label' => 'By Phone Number'],
                'demographic' => ['active' => true, 'label' => 'By Demographics'],
            ]),
        ]);
    }

    /**
     * Show BVN verification page
     */
    public function bvn()
    {
        $userId = Auth::id();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        $bvnPrice = config('services.verification.bvn_price', 150);

        return Inertia::render('Verification/Bvn', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => $wallet->total_balance,
            ],
            'price' => $bvnPrice,
        ]);
    }

    /**
     * Verify NIN
     */
    public function verifyNin(Request $request)
    {
        $validated = $request->validate([
            'verification_type' => 'required|in:nin,phone,demographic',
            'nin_number' => 'required_if:verification_type,nin|string|size:11',
            'phone_number' => 'required_if:verification_type,phone|string|min:10|max:11',
            'last_name' => 'required_if:verification_type,demographic|string',
            'first_name' => 'required_if:verification_type,demographic|string',
            'gender' => 'required_if:verification_type,demographic|in:male,female',
            'date_of_birth' => 'required_if:verification_type,demographic|date',
        ]);

        $result = $this->verificationService->verifyNin(
            Auth::id(),
            $validated
        );

        if ($result['success']) {
            return redirect()->back()->with([
                'success' => $result['message'],
                'verification_data' => $result['data'],
            ]);
        }

        return back()->withErrors(['verification' => $result['message']]);
    }

    /**
     * Verify BVN
     */
    public function verifyBvn(Request $request)
    {
        $validated = $request->validate([
            'bvn_number' => 'required|string|size:11',
        ]);

        $result = $this->verificationService->verifyBvn(
            Auth::id(),
            $validated['bvn_number']
        );

        if ($result['success']) {
            return redirect()->back()->with([
                'success' => $result['message'],
                'verification_data' => $result['data'],
            ]);
        }

        return back()->withErrors(['bvn_number' => $result['message']]);
    }

    /**
     * Get verification history
     */
    public function history(Request $request)
    {
        $type = $request->input('type'); // 'nin', 'bvn', or null for all

        $history = $this->verificationService->getVerificationHistory(
            Auth::id(),
            $type
        );

        return Inertia::render('Verification/History', [
            'history' => $history,
            'filter' => $type,
        ]);
    }
}
