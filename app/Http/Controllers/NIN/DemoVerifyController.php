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
 * NIN Demo Verification Controller
 * Verifies by name, gender, DOB — always billed at premium price
 * Supports v1 (ArewaSmart demo API)
 */
class DemoVerifyController extends Controller
{
    use NinWalletTrait;

    /**
     * Show the Demo Verification page
     */
    public function index()
    {
        $user = Auth::user();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        return Inertia::render('NIN/DemoVerify/Index', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => (float) $wallet->total_balance,
            ],
            'price' => $this->getSlipPrice('premium'),
            'transactions' => NinValidation::where('user_id', $user->id)
                ->where('comment', 'like', '%demo%')
                ->orderBy('created_at', 'desc')
                ->paginate(10),
        ]);
    }

    /**
     * Demo verify — Provider 1 (ArewaSmart)
     */
    public function verifyV1(Request $request)
    {
        return $this->processVerification($request, 'v1');
    }

    /**
     * Demo verify — Provider 2 (ArewaSmart alt)
     */
    public function verifyV2(Request $request)
    {
        return $this->processVerification($request, 'v2');
    }

    protected function processVerification(Request $request, string $version)
    {
        $validated = $request->validate([
            'firstName'   => 'required|string|min:2|max:100',
            'lastName'    => 'required|string|min:2|max:100',
            'gender'      => 'required|string|in:M,F,male,female,Male,Female',
            'dateOfBirth' => ['required', 'string', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
            'ref'         => 'nullable|string|max:255',
        ]);

        $user   = Auth::user();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );
        $price = $this->getSlipPrice('premium');

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
                ->post(config('services.nin.base_url') . '/api/v1/nin/demo', [
                    'firstName'   => $validated['firstName'],
                    'lastName'    => $validated['lastName'],
                    'gender'      => $validated['gender'],
                    'dateOfBirth' => $validated['dateOfBirth'],
                    'ref'         => $validated['ref'] ?? null,
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
                    'comment'      => "Demo verify {$version}: {$validated['firstName']} {$validated['lastName']}",
                    'old_balance'  => $oldBalance,
                    'new_balance'  => $wallet->fresh()->total_balance,
                    'reference'    => $reference,
                    'validated_at' => now(),
                ]);

                return back()->with([
                    'success'           => 'NIN verified via demo successfully.',
                    'verification_data' => $body,
                ]);
            }

            $this->creditWallet($wallet, $price);

            $errorMessage = $body['message'] ?? $body['error'] ?? $body['msg'] ?? 'Demo verification failed';
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
            Log::error("NIN Demo Verify {$version} error: " . $e->getMessage());
            return back()->withErrors(['message' => 'Network error: ' . $e->getMessage()]);
        }
    }
}
