<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Models\NinValidation;
use App\Models\ServicePrice;
use App\Models\SlipType;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * NIN Verification Controller (by NIN number)
 * Supports v1 (Prembly) and v2 (ArewaSmart)
 * Charges only verification fee - slip download is separate
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

        // Get verification history (NinValidation records)
        $verifications = NinValidation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Get slip download transactions
        $slipDownloads = Transaction::where('user_id', $user->id)
            ->where('type', Transaction::TYPE_NIN_SLIP_DOWNLOAD)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'type' => 'slip_download',
                    'slip_type' => $transaction->details['slip_type'] ?? 'unknown',
                    'slip_name' => $transaction->details['slip_name'] ?? 'NIN Slip',
                    'nin' => $transaction->details['nin'] ?? null,
                    'amount' => (float) $transaction->amount,
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at,
                    'date' => $transaction->created_at->format('M d, Y H:i'),
                ];
            });

        return Inertia::render('NIN/Verify/Index', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'bonus_balance' => (float) $wallet->bonus_balance,
                'total_balance' => (float) $wallet->total_balance,
            ],
            'verificationPrice' => $this->getVerificationPrice(),
            'slipTypes' => $this->getActiveSlipTypes(),
            'transactions' => $verifications,
            'slipDownloads' => $slipDownloads,
        ]);
    }

    /**
     * Verify NIN using Provider 1 (Prembly)
     */
    public function verifyV1(Request $request)
    {
        $validated = $request->validate([
            'idType'  => 'required|string|in:nin,phone',
            'idValue' => 'required|string|min:10|max:15',
        ]);

        return $this->processVerification($validated, 'v1');
    }

    /**
     * Verify NIN using Provider 2 (ArewaSmart)
     */
    public function verifyV2(Request $request)
    {
        $validated = $request->validate([
            'idType'  => 'required|string|in:nin,phone',
            'idValue' => 'required|string|min:10|max:15',
        ]);

        return $this->processVerification($validated, 'v2');
    }

    /**
     * Process NIN verification - charges only verification fee
     */
    protected function processVerification(array $data, string $version)
    {
        $user = Auth::user();
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'bonus_balance' => 0]
        );

        // Get VERIFICATION price only (not slip price)
        $verificationPrice = $this->getVerificationPrice();

        if ($wallet->total_balance < $verificationPrice) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = $wallet->total_balance;
        $reference = NinValidation::generateReference();

        try {
            DB::beginTransaction();

            // Deduct verification fee
            $this->debitWallet($wallet, $verificationPrice);

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
                    'slipType' => 'standard', // API still needs this, but we don't charge for it
                ]);

            $body = $response->json();
            $rawBody = $response->body();
            $httpStatus = $response->status();

            if ($response->successful() && !isset($body['error'])) {
                // Create verification record
                $validation = NinValidation::create([
                    'user_id'              => $user->id,
                    'nin'                  => $body['nin'] ?? $data['idValue'],
                    'id_type'              => $data['idType'],
                    'id_value'             => $data['idValue'],
                    'status'               => 'completed',
                    'result'               => json_encode($body),
                    'comment'              => "NIN verify {$version} ({$data['idType']})",
                    'old_balance'          => $oldBalance,
                    'new_balance'          => $wallet->fresh()->total_balance,
                    'reference'            => $reference,
                    'provider'             => $version,
                    'verification_fee'     => $verificationPrice,
                    'is_verified'          => true,
                    'validated_at'         => now(),
                ]);

                // Create transaction record for verification
                Transaction::createVerification(
                    $user->id,
                    $verificationPrice,
                    Transaction::TYPE_NIN_VERIFICATION,
                    [
                        'validation_id' => $validation->id,
                        'nin' => $body['nin'] ?? $data['idValue'],
                        'id_type' => $data['idType'],
                        'provider' => $version,
                    ]
                );

                DB::commit();

                // Include validation_id in the verification data for persistence
                $body['validation_id'] = $validation->id;

                return back()->with([
                    'success'           => 'NIN verified successfully.',
                    'verification_data' => $body,
                    'validation_id'     => $validation->id,
                ]);
            }

            // Refund on failure
            $this->creditWallet($wallet, $verificationPrice);

            $errorMessage = $body['message'] ?? $body['error'] ?? $body['msg'] ?? 'Verification failed';
            $resultPayload = $body ? json_encode($body) : json_encode([
                'http_status' => $httpStatus,
                'raw_response' => substr($rawBody, 0, 2000),
            ]);

            NinValidation::create([
                'user_id'     => $user->id,
                'nin'         => $data['idValue'],
                'id_type'     => $data['idType'],
                'id_value'    => $data['idValue'],
                'status'      => 'failed',
                'result'      => $resultPayload,
                'comment'     => "[HTTP {$httpStatus}] {$errorMessage}",
                'old_balance' => $oldBalance,
                'new_balance' => $wallet->fresh()->total_balance,
                'reference'   => $reference,
                'provider'    => $version,
                'is_verified' => false,
            ]);

            DB::commit();

            return back()->withErrors(['message' => "[HTTP {$httpStatus}] {$errorMessage}"]);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->creditWallet($wallet, $verificationPrice);
            Log::error("NIN Verify {$version} error: " . $e->getMessage());
            return back()->withErrors(['message' => 'Network error: ' . $e->getMessage()]);
        }
    }
}
//