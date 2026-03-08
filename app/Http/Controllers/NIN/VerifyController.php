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
 * NIN Verification Controller (by NIN number)
 * Supports v1 (Prembly) and v2 (ArewaSmart)
 */
class VerifyController extends Controller
{
    use NinWalletTrait;
    /**
     * Show the NIN Verification page
     */
    public function index()
    {
        $user = Auth::user();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        return Inertia::render('NIN/Verify/Index', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => (float) $wallet->total_balance,
            ],
            'prices' => [
                'premium' => config('services.nin.prices.premium', 150),
                'standard' => config('services.nin.prices.standard', 100),
                'regular' => config('services.nin.prices.regular', 50),
            ],
            'transactions' => NinValidation::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10),
        ]);
    }

    /**
     * Verify NIN using Provider 1 (Prembly)
     */
    public function verifyV1(Request $request)
    {
        $validated = $request->validate([
            'idType'   => 'required|string|in:nin,phone',
            'idValue'  => 'required|string|min:10|max:15',
            'slipType' => 'required|string|in:premium,standard,regular',
        ]);

        return $this->processVerification($validated, 'v1');
    }

    /**
     * Verify NIN using Provider 2 (ArewaSmart)
     */
    public function verifyV2(Request $request)
    {
        $validated = $request->validate([
            'idType'   => 'required|string|in:nin,phone',
            'idValue'  => 'required|string|min:10|max:15',
            'slipType' => 'required|string|in:premium,standard,regular',
        ]);

        return $this->processVerification($validated, 'v2');
    }

    /**
     * Process NIN verification for either provider
     */
    protected function processVerification(array $data, string $version)
    {
        $user = Auth::user();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        $price = $this->getPrice($data['slipType']);

        if ($wallet->total_balance < $price) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = $wallet->total_balance;
        $this->debitWallet($wallet, $price);

        $reference = 'Verify_' . now()->timestamp . rand(1000, 9999);

        try {
            $endpoint = $version === 'v1'
                ? config('services.nin.base_url') . '/api/v1/nin/verify_1'
                : config('services.nin.base_url') . '/api/v1/nin/verify_2';

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.nin.api_key'),
                    'Content-Type'  => 'application/json',
                ])
                ->post($endpoint, [
                    'idType'   => $data['idType'],
                    'idValue'  => $data['idValue'],
                    'slipType' => $data['slipType'],
                ]);

            $body = $response->json();
            $rawBody = $response->body();
            $httpStatus = $response->status();

            if ($response->successful() && !isset($body['error'])) {
                // Log the transaction
                NinValidation::create([
                    'user_id'      => $user->id,
                    'nin'          => $body['nin'] ?? $data['idValue'],
                    'status'       => 'completed',
                    'result'       => json_encode($body),
                    'comment'      => "NIN verify {$version} ({$data['idType']}) — {$data['slipType']}",
                    'old_balance'  => $oldBalance,
                    'new_balance'  => $wallet->fresh()->total_balance,
                    'reference'    => $reference,
                    'validated_at' => now(),
                ]);

                return back()->with([
                    'success'           => 'NIN verified successfully.',
                    'verification_data' => $body,
                ]);
            }

            // Refund on failure
            $this->creditWallet($wallet, $price);

            $errorMessage = $body['message'] ?? $body['error'] ?? $body['msg'] ?? 'Verification failed';
            $resultPayload = $body ? json_encode($body) : json_encode([
                'http_status' => $httpStatus,
                'raw_response' => substr($rawBody, 0, 2000),
            ]);

            NinValidation::create([
                'user_id'     => $user->id,
                'nin'         => $data['idValue'],
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
            Log::error("NIN Verify {$version} error: " . $e->getMessage());
            return back()->withErrors(['message' => 'Network error: ' . $e->getMessage()]);
        }
    }

}

//