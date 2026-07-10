<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;

/**
 * The only balance-mutation path for the data module. Each movement locks the
 * user row, updates users.balance, and writes a wallet_entries ledger row inside
 * one DB transaction so the balance stays a faithful materialization of the
 * ledger.
 */
class WalletLedger
{
    /**
     * Atomically debit the user. Returns false when funds are insufficient.
     *
     * @return array{old: float, new: float}|false
     */
    public function debit(User $user, float $amount, string $reason, ?string $dataTransactionId = null): array|false
    {
        return DB::transaction(function () use ($user, $amount, $reason, $dataTransactionId) {
            $fresh = User::whereKey($user->getKey())->lockForUpdate()->first();

            if (! $fresh || (float) $fresh->balance < $amount) {
                return false;
            }

            $old = (float) $fresh->balance;
            $new = $old - $amount;

            $fresh->forceFill(['balance' => $new])->save();
            $user->setRawAttributes($fresh->getAttributes(), true);

            $this->record($user, 'debit', $amount, $new, $reason, $dataTransactionId);

            return ['old' => $old, 'new' => $new];
        });
    }

    /**
     * Atomically credit the user (e.g. a refund).
     *
     * @return array{old: float, new: float}
     */
    public function credit(User $user, float $amount, string $reason, ?string $dataTransactionId = null): array
    {
        return DB::transaction(function () use ($user, $amount, $reason, $dataTransactionId) {
            $fresh = User::whereKey($user->getKey())->lockForUpdate()->first();

            $old = (float) $fresh->balance;
            $new = $old + $amount;

            $fresh->forceFill(['balance' => $new])->save();
            $user->setRawAttributes($fresh->getAttributes(), true);

            $this->record($user, 'credit', $amount, $new, $reason, $dataTransactionId);

            return ['old' => $old, 'new' => $new];
        });
    }

    private function record(User $user, string $direction, float $amount, float $balanceAfter, string $reason, ?string $dataTransactionId): WalletEntry
    {
        return WalletEntry::create([
            'user_id' => $user->getKey(),
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reason' => $reason,
            'data_transaction_id' => $dataTransactionId,
        ]);
    }
}
