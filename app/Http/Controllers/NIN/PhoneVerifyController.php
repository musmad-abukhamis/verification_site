<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Models\NinValidation;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * NIN Phone Verification Controller
 * Supports v1 (ArewaSmart phone API) — always billed at premium price
 */
class PhoneVerifyController extends Controller
{
    use NinWalletTrait;

    /**
     * Show the Phone Verification page
     */
    public function index()
    {
        $user = Auth::user();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        return Inertia::render('NIN/PhoneVerify/Index', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => (float) $wallet->total_balance,
            ],
            'price' => $this->getPremiumPrice(),
            'transactions' => NinValidation::where('user_id', $user->id)
                ->where('comment', 'like', '%phone%')
                ->orderBy('created_at', 'desc')
                ->paginate(10),
        ]);
    }

    /**
     * Verify NIN via phone — Provider 1 (ArewaSmart)
     */
    public function verifyV1(Request $request)
    {
        return $this->processVerification($request, 'v1');
    }

    /**
     * Verify NIN via phone — Provider 2 (ArewaSmart alt)
     */
    public function verifyV2(Request $request)
    {
        return $this->processVerification($request, 'v2');
    }

    protected function processVerification(Request $request, string $version)
    {
        $validated = $request->validate([
            'value' => 'required|string|min:10|max:15|regex:/^0[0-9]{10}$/',
            'ref'   => 'nullable|string|max:255',
        ]);

        $user   = Auth::user();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );
        $price = $this->getPremiumPrice();

        if ($wallet->total_balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = $wallet->total_balance;
        $this->debitWallet($wallet, $price);
        $reference = 'Verify_' . now()->timestamp . rand(1000, 9999);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.nin.api_key'),
                    'Content-Type'  => 'application/json',
                ])
                ->post(config('services.nin.base_url') . '/api/v1/nin/phone', [
                    'value' => $validated['value'],
                    'ref'   => $validated['ref'] ?? null,
                ]);

            $body = $response->json();
            $rawBody = $response->body();
            $httpStatus = $response->status();

            if ($response->successful() && !isset($body['error'])) {
                NinValidation::create([
                    'user_id'      => $user->id,
                    'nin'          => $body['nin'] ?? null,
                    'status'       => 'completed',
                    'result'       => json_encode($body),
                    'comment'      => "Phone verify {$version}: {$validated['value']}",
                    'old_balance'  => $oldBalance,
                    'new_balance'  => $wallet->fresh()->total_balance,
                    'reference'    => $reference,
                    'validated_at' => now(),
                ]);

                return back()->with([
                    'success'           => 'NIN retrieved via phone successfully.',
                    'verification_data' => $body,
                ]);
            }

            $this->creditWallet($wallet, $price);

            $errorMessage = $body['message'] ?? $body['error'] ?? $body['msg'] ?? 'Phone verification failed';
            $resultPayload = $body ? json_encode($body) : json_encode([
                'http_status'  => $httpStatus,
                'raw_response' => substr($rawBody, 0, 2000),
            ]);

            NinValidation::create([
                'user_id'     => $user->id,
                'nin'         => null,
                'status'      => 'failed',
                'result'      => $resultPayload,
                'comment'     => "[HTTP {$httpStatus}] {$errorMessage}",
                'old_balance' => $oldBalance,
                'new_balance' => $wallet->fresh()->total_balance,
                'reference'   => $reference,
            ]);

            return back()->withErrors(['message' => "[HTTP {$httpStatus}] {$errorMessage}"]);
        } catch (\Exception $e) {
            $this->creditWallet($wallet, $price);
            Log::error("NIN Phone Verify {$version} error: " . $e->getMessage());
            return back()->withErrors(['message' => 'Network error: ' . $e->getMessage()]);
        }
    }
}
