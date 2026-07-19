<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountKyc;
use App\Models\FundingSetting;
use App\Models\User;
use App\Models\WalletHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PayVessel funding webhook.
 *
 * Port of nimcweb's app/api/webhooks/payvessel/route.ts, with the security
 * restored. That version had BOTH the signature and IP checks commented out,
 * so any POST to the URL credited any wallet by email -- unauthenticated,
 * unlimited money. The signature check there could never have passed: it read
 * the header as HTTP_PAYVESSEL_HTTP_SIGNATURE, which is the PHP $_SERVER
 * spelling of "Payvessel-Http-Signature", so the lookup always returned null.
 */
class PayVesselWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = config('services.payvessel.secret');

        if (! $secret) {
            Log::error('PayVessel webhook: secret not configured');

            return response()->json(['error' => 'Server configuration error'], 500);
        }

        // Signature is over the RAW body. Re-encoding the parsed JSON would
        // change key order/spacing and never match.
        $payload = $request->getContent();
        $signature = $request->header('Payvessel-Http-Signature');
        $expected = hash_hmac('sha512', $payload, $secret);

        if (! $signature || ! hash_equals($expected, $signature)) {
            Log::warning('PayVessel webhook: invalid signature', [
                'ip' => $request->ip(),
                // Header name is the likeliest thing to be wrong if PayVessel
                // changes it; log what actually arrived to make that diagnosable
                // without weakening the check.
                'headers' => array_keys($request->headers->all()),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $allowedIps = config('services.payvessel.webhook_ips');

        if ($allowedIps && ! in_array($request->ip(), $allowedIps, true)) {
            Log::warning('PayVessel webhook: rejected source address', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Permission denied, invalid IP address'], 403);
        }

        $this->credit($request->json()->all());

        return response()->json(['message' => 'success']);
    }

    private function credit(array $data): void
    {
        $reference = $data['transaction']['reference'] ?? null;
        $gross = (float) ($data['order']['amount'] ?? 0);
        $settlement = (float) ($data['order']['settlement_amount'] ?? 0);

        if (! $reference || $gross <= 0) {
            Log::warning('PayVessel webhook: incomplete payload', ['reference' => $reference]);

            return;
        }

        // Idempotency: the ledger row id is pinned to PayVessel's reference, so
        // a retried delivery cannot double-credit.
        if (WalletHistory::whereKey($reference)->exists()) {
            return;
        }

        $user = $this->resolveUser($data);

        if (! $user) {
            // Money has arrived that we cannot attribute. Loud, because it needs
            // a human to reconcile.
            Log::error('PayVessel webhook: no user for payment', [
                'reference' => $reference,
                'account' => $data['order']['account_number'] ?? null,
                'email' => $data['customer']['email'] ?? null,
            ]);

            return;
        }

        // Admin-configurable: credit what the customer sent, or what actually
        // settled after PayVessel's fee.
        $amount = FundingSetting::creditsNetOfFees() && $settlement > 0
            ? $settlement
            : $gross;

        $user->credit($amount, false, [
            'id' => $reference,
            'fundingtype' => 'automatic-funding',
        ]);

        Log::info('PayVessel webhook: funded wallet', [
            'userId' => $user->id,
            'reference' => $reference,
            'amount' => $amount,
            'gross' => $gross,
        ]);
    }

    /**
     * Prefer the credited account number: it is issued by us and tied to one
     * user. Email is the fallback only, since it is weaker -- a shared or
     * changed address would attribute money to the wrong account.
     */
    private function resolveUser(array $data): ?User
    {
        $accountNumber = $data['order']['account_number']
            ?? $data['account']['account_number']
            ?? null;

        if ($accountNumber) {
            $columns = ['palmpay', 'palmpay2', 'Ninesp', 'moniepoint', 'wema', 'providus', 'sterling', 'opay', 'fidelity'];

            $kyc = AccountKyc::with('user')
                ->where(function ($query) use ($columns, $accountNumber) {
                    foreach ($columns as $column) {
                        $query->orWhere($column, $accountNumber);
                    }
                })
                ->first();

            if ($kyc?->user) {
                return $kyc->user;
            }
        }

        $email = $data['customer']['email'] ?? null;

        return $email
            ? User::whereRaw('lower(email) = ?', [mb_strtolower($email)])->first()
            : null;
    }
}
