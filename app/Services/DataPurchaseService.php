<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\ProcessDataPurchase;
use App\Models\DataTransaction;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Entry point for every data purchase (web + API). Debits the wallet, records a
 * pending transaction, and queues fulfilment. The vendor call itself never runs
 * inline — it happens in ProcessDataPurchase on the queue.
 */
class DataPurchaseService
{
    public function __construct(private readonly WalletLedger $ledger) {}

    /**
     * @param  array{plan_id: int|string, phone: string, ported?: bool, client_ref?: string|null, network?: string|null}  $data
     */
    public function initiate(User $user, array $data): DataTransaction
    {
        $clientRef = $data['client_ref'] ?? null;

        // Idempotency: a repeat of the same client_ref within 10 minutes returns
        // the original transaction instead of charging again (double-tap guard).
        if ($clientRef) {
            $existing = DataTransaction::query()
                ->where('user_id', $user->getKey())
                ->where('client_ref', $clientRef)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->latest('created_at')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // plan_id from the caller is the PUBLIC code, not the primary key.
        $plan = Plan::active()->byCode($data['plan_id'])->firstOrFail();

        // Never trust a client-supplied price — resolve it from the plan + role.
        $price = $plan->priceForRole($user->role);

        // Generate the reference first so the debit ledger row can link straight
        // to the transaction it pays for.
        $reference = $this->generateReference();

        $movement = $this->ledger->debit($user, $price, 'purchase', $reference);

        if ($movement === false) {
            throw new InsufficientBalanceException;
        }

        $txn = DataTransaction::create([
            'id' => $reference,
            'user_id' => $user->getKey(),
            'plan_id' => $plan->id,
            'status' => 'pending',
            'network' => $plan->network,
            'type' => $plan->type,
            'plan_name' => $plan->name,
            'price' => $price,
            'phone' => $this->cleanPhone($data['phone']),
            'ported' => (bool) ($data['ported'] ?? false),
            'attempts' => 0,
            'oldbal' => $movement['old'],
            'newbal' => $movement['new'],
            'client_ref' => $clientRef,
        ]);

        ProcessDataPurchase::dispatch($txn->getKey());

        return $txn;
    }

    private function cleanPhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }

    private function generateReference(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $reference = 'Data_'.((int) (microtime(true) * 1000)).'_'.Str::upper(Str::random(6));

            if (! DataTransaction::whereKey($reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique transaction reference');
    }
}
