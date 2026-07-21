<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Validation;
use App\Services\Nin\NinProviderManager;
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
    public function index(NinProviderManager $providers)
    {
        $user = Auth::user();

        // Get verification history (validation records)
        $verifications = Validation::where('userId', $user->id)
            ->orderByDesc('createdAt')
            ->paginate(10)
            ->through(fn ($r) => $this->presentNinRecord($r));

        // Get slip download transactions
        $slipDownloads = Transaction::where('userId', $user->id)
            ->where('type', Transaction::TYPE_NIN_SLIP_DOWNLOAD)
            ->orderByDesc('createdAt')
            ->get()
            ->map(function (Transaction $transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'type' => 'slip_download',
                    'slip_type' => $transaction->details['slip_type'] ?? 'unknown',
                    'slip_name' => $transaction->details['slip_name'] ?? 'NIN Slip',
                    'nin' => $transaction->details['nin'] ?? null,
                    'amount' => (float) $transaction->amount,
                    'status' => $transaction->status,
                    'created_at' => $transaction->createdAt,
                    'date' => $transaction->createdAt?->format('M d, Y H:i'),
                ];
            });

        return Inertia::render('NIN/Verify/Index', [
            'wallet' => $this->walletPayload($user),
            'verificationPrice' => $this->getVerificationPrice(),
            'slipTypes' => $this->getActiveSlipTypes(),
            'transactions' => $verifications,
            'slipDownloads' => $slipDownloads,
            // Modular provider catalog for the dynamic verification UI.
            'providers' => $providers->forFrontend(),
            'methodCatalog' => NinProviderManager::methodCatalog(),
        ]);
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
     * Verify NIN using Provider 1 (Prembly)
     */
    public function verifyV1(Request $request)
    {
        $validated = $request->validate([
            'idType' => 'required|string|in:nin,phone',
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
            'idType' => 'required|string|in:nin,phone',
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

        // Get VERIFICATION price only (not slip price)
        $verificationPrice = $this->getVerificationPrice();

        if ($verificationPrice === null) {
            return $this->unpricedService();
        }

        if ((float) $user->balance < $verificationPrice) {
            return back()->withErrors(['message' => 'Insufficient wallet balance. Please fund your wallet.']);
        }

        $oldBalance = (float) $user->balance;
        $reference = Validation::generateReference();

        try {
            DB::beginTransaction();

            // Deduct verification fee
            $this->debitWallet($user, $verificationPrice, ['fundingtype' => 'nin_verification']);

            $endpoint = $version === 'v1'
                ? config('services.nin.base_url').'/api/v1/nin/verify_1'
                : config('services.nin.base_url').'/api/v1/nin/verify_2';

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('services.nin.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'idType' => $data['idType'],
                    'idValue' => $data['idValue'],
                    'slipType' => 'standard', // API still needs this, but we don't charge for it
                ]);

            $body = $response->json();
            $rawBody = $response->body();
            $httpStatus = $response->status();

            if ($response->successful() && ! isset($body['error'])) {
                // Create verification record
                $validation = Validation::create([
                    'nin' => $body['nin'] ?? $data['idValue'],
                    'status' => 'completed',
                    'result' => json_encode($body),
                    'comment' => "NIN verify {$version} ({$data['idType']}) [{$data['idValue']}]",
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
                ]);

                // Create transaction record for verification
                Transaction::createVerification(
                    $user->id,
                    $verificationPrice,
                    Transaction::TYPE_NIN_VERIFICATION,
                    [
                        'reference' => $reference,
                        'validation_id' => $validation->id,
                        'nin' => $body['nin'] ?? $data['idValue'],
                        'id_type' => $data['idType'],
                        'provider' => $version,
                        'old_balance' => $oldBalance,
                        'new_balance' => (float) $user->balance,
                    ]
                );

                DB::commit();

                // Include validation_id in the verification data for persistence
                $body['validation_id'] = $validation->id;

                return back()->with([
                    'success' => 'NIN verified successfully.',
                    'verification_data' => $body,
                    'validation_id' => $validation->id,
                ]);
            }

            // Refund on failure
            $this->creditWallet($user, $verificationPrice, ['fundingtype' => 'refund', 'status' => 'refund']);

            $errorMessage = $body['message'] ?? $body['error'] ?? $body['msg'] ?? 'Verification failed';
            $resultPayload = $body ? json_encode($body) : json_encode([
                'http_status' => $httpStatus,
                'raw_response' => substr($rawBody, 0, 2000),
            ]);

            Validation::create([
                'nin' => $data['idValue'],
                'status' => 'failed',
                'result' => $resultPayload,
                'comment' => "[HTTP {$httpStatus}] {$errorMessage}",
                'oldBal' => $oldBalance,
                'newBal' => (float) $user->balance,
                'userId' => $user->id,
            ]);

            DB::commit();

            return back()->withErrors(['message' => "[HTTP {$httpStatus}] {$errorMessage}"]);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->creditWallet($user, $verificationPrice, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error("NIN Verify {$version} error: ".$e->getMessage());

            return back()->withErrors(['message' => 'Network error: '.$e->getMessage()]);
        }
    }
}
