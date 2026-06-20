<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountKyc;
use App\Models\WalletHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Billstack (Wiaxy) funding webhook.
 *
 * Port of nimcweb's app/api/webhooks/billstack/route.ts. On a reserved-account
 * payment we look up the owning user by the credited account number and credit
 * their wallet. Idempotent on the Billstack reference (used as the ledger id).
 */
class BillstackWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('x-wiaxy-signature');
        $token = config('services.billstack.token');

        if (! $token) {
            Log::error('Billstack webhook: token not configured');

            return response()->json(['error' => 'Server configuration error'], 500);
        }

        if (! $signature || ! hash_equals(md5($token), $signature)) {
            Log::warning('Billstack webhook: invalid signature');

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $payload = $request->json()->all();

        if (($payload['event'] ?? null) === 'PAYMENT_NOTIFICATION'
            && ($payload['data']['type'] ?? null) === 'RESERVED_ACCOUNT_TRANSACTION') {
            $this->creditFunding($payload['data']);
        }

        return response()->json(['success' => true, 'message' => 'Webhook processed successfully']);
    }

    private function creditFunding(array $data): void
    {
        $reference = $data['reference'] ?? null;
        $accountNumber = $data['account']['account_number'] ?? null;
        $amount = (float) ($data['amount'] ?? 0);

        if (! $reference || ! $accountNumber || $amount <= 0) {
            Log::warning('Billstack webhook: incomplete payload', ['reference' => $reference]);

            return;
        }

        // Idempotency — the ledger row id is pinned to the Billstack reference.
        if (WalletHistory::whereKey($reference)->exists()) {
            return;
        }

        // Find the owning user by whichever bank column holds this account number.
        $columns = ['palmpay', 'palmpay2', 'moniepoint', 'wema', 'providus', 'sterling', 'opay', 'fidelity', 'Ninesp'];

        $accountKyc = AccountKyc::with('user')
            ->where(function ($q) use ($columns, $accountNumber) {
                foreach ($columns as $column) {
                    $q->orWhere($column, $accountNumber);
                }
            })
            ->first();

        if (! $accountKyc || ! $accountKyc->user) {
            Log::error('Billstack webhook: no user for account number', ['account' => $accountNumber]);

            return;
        }

        $accountKyc->user->credit($amount, false, [
            'id' => $reference,
            'fundingtype' => 'automatic-funding',
        ]);

        Log::info('Billstack webhook: funded wallet', [
            'userId' => $accountKyc->userId,
            'reference' => $reference,
            'amount' => $amount,
        ]);
    }
}
