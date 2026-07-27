<?php

namespace App\Http\Controllers\NIN;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Validation;
use App\Services\Nin\NinProviderManager;
use App\Services\Nin\Providers\RoutedProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * NIN Verification (by NIN or phone).
 *
 * The provider is chosen by the routing chain in Admin > Verification, with
 * failover — there is no v1/v2 any more, because those were just two hardcoded
 * providers. Charges the verification fee only; slip download is billed
 * separately.
 */
class VerifyController extends Controller
{
    use NinWalletTrait;

    public function __construct(private readonly RoutedProvider $routed) {}

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
     * Verify a NIN or phone number.
     *
     * One endpoint: the v1/v2 split only ever chose between two hardcoded
     * providers, which is now the job of the `nin.verify` / `nin.phone` routing
     * chain in Admin > Verification.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'idType' => 'required|string|in:nin,phone',
            'idValue' => 'required|string|min:10|max:15',
        ]);

        return $this->processVerification($validated);
    }

    /**
     * Charge the verification fee, run the routed chain, refund on failure.
     */
    protected function processVerification(array $data)
    {
        $user = Auth::user();

        // Get VERIFICATION price only (not slip price)
        $verificationPrice = $this->getVerificationPrice($user);

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

            $service = $data['idType'] === 'phone' ? 'nin.phone' : 'nin.verify';

            $result = $data['idType'] === 'phone'
                ? $this->routed->verifyByPhone($data['idValue'])
                : $this->routed->verifyByNin($data['idValue']);

            if ($result->success) {
                $body = $result->data ?? [];

                $validation = Validation::create([
                    'nin' => $body['nin'] ?? $data['idValue'],
                    'status' => 'completed',
                    'result' => json_encode($body),
                    'comment' => "NIN verify ({$data['idType']}) [{$data['idValue']}] via ".($body['provider'] ?? 'routing'),
                    'oldBal' => $oldBalance,
                    'newBal' => (float) $user->balance,
                    'userId' => $user->id,
                ]);

                Transaction::createVerification(
                    $user->id,
                    $verificationPrice,
                    Transaction::TYPE_NIN_VERIFICATION,
                    [
                        'reference' => $reference,
                        'validation_id' => $validation->id,
                        'nin' => $body['nin'] ?? $data['idValue'],
                        'id_type' => $data['idType'],
                        // The provider that actually answered, not a version tag.
                        'provider' => $body['provider'] ?? null,
                        'old_balance' => $oldBalance,
                        'new_balance' => (float) $user->balance,
                    ]
                );

                DB::commit();

                $body['validation_id'] = $validation->id;

                return back()->with([
                    'success' => 'NIN verified successfully.',
                    'verification_data' => $body,
                    'validation_id' => $validation->id,
                ]);
            }

            // Every routed provider declined (or none is configured) — refund.
            $this->creditWallet($user, $verificationPrice, ['fundingtype' => 'refund', 'status' => 'refund']);

            $errorMessage = $result->message ?? 'Verification failed';

            Validation::create([
                'nin' => $data['idValue'],
                'status' => 'failed',
                'result' => json_encode($result->raw ?? ['message' => $errorMessage]),
                'comment' => "[{$service}] {$errorMessage}",
                'oldBal' => $oldBalance,
                'newBal' => (float) $user->balance,
                'userId' => $user->id,
            ]);

            DB::commit();

            return back()->withErrors(['message' => $errorMessage]);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->creditWallet($user, $verificationPrice, ['fundingtype' => 'refund', 'status' => 'refund']);
            Log::error('NIN Verify error: '.$e->getMessage());

            return back()->withErrors(['message' => 'Network error: '.$e->getMessage()]);
        }
    }
}
