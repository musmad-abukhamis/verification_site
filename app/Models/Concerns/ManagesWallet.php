<?php

namespace App\Models\Concerns;

use App\Models\WalletHistory;
use Illuminate\Support\Facades\DB;

/**
 * Wallet behaviour for the User model. Replaces the old standalone `Wallet`
 * table — balance now lives on `users.balance` and every movement is recorded
 * in the Prisma `wallethistory` ledger.
 *
 * A `wallet` accessor returns the user itself so legacy `$user->wallet->debit()`
 * / `$user->wallet->balance` call sites keep working.
 */
trait ManagesWallet
{
    /**
     * Compatibility shim: `$user->wallet` resolves back to the user, which now
     * carries the balance and the debit()/credit() helpers directly.
     */
    public function getWalletAttribute(): self
    {
        return $this;
    }

    /**
     * The old Wallet had a separate bonus balance; there is no bonus column in
     * the new schema, so total balance is simply the balance.
     */
    public function getTotalBalanceAttribute(): float
    {
        return (float) $this->balance;
    }

    /**
     * Read-only compatibility accessor — there is no bonus balance in the new
     * schema, so it is always 0. (Old write paths that manipulated bonus
     * balance have been migrated to debit()/credit().)
     */
    public function getBonusBalanceAttribute(): float
    {
        return 0.0;
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return (float) $this->balance >= $amount;
    }

    /**
     * Atomically debit the wallet. Returns false when funds are insufficient.
     * The second argument is kept only for signature compatibility with the
     * old Wallet::debit($amount, $useBonus) calls.
     */
    public function debit(float $amount, bool $useBonus = false, array $meta = []): bool
    {
        return (bool) DB::transaction(function () use ($amount, $meta) {
            $fresh = static::whereKey($this->getKey())->lockForUpdate()->first();

            if (! $fresh || (float) $fresh->balance < $amount) {
                return false;
            }

            $oldBalance = (float) $fresh->balance;
            $newBalance = $oldBalance - $amount;

            $fresh->forceFill(['balance' => $newBalance])->save();
            $this->setRawAttributes($fresh->getAttributes(), true);

            $this->recordHistory('debit', $amount, $oldBalance, $newBalance, $meta);

            return true;
        });
    }

    /**
     * Credit the wallet. The second argument is kept for signature
     * compatibility with the old Wallet::credit($amount, $isBonus).
     */
    public function credit(float $amount, bool $isBonus = false, array $meta = []): void
    {
        DB::transaction(function () use ($amount, $meta) {
            $fresh = static::whereKey($this->getKey())->lockForUpdate()->first();

            $oldBalance = (float) $fresh->balance;
            $newBalance = $oldBalance + $amount;

            $fresh->forceFill(['balance' => $newBalance])->save();
            $this->setRawAttributes($fresh->getAttributes(), true);

            $this->recordHistory('credit', $amount, $oldBalance, $newBalance, $meta);
        });
    }

    protected function recordHistory(string $type, float $amount, float $oldBalance, float $newBalance, array $meta = []): WalletHistory
    {
        $attributes = [
            'type' => $type,
            'status' => $meta['status'] ?? 'success',
            'fundingtype' => $meta['fundingtype'] ?? ($type === 'credit' ? 'wallet-funding' : 'service-charge'),
            'amount' => $amount,
            'oldbal' => $oldBalance,
            'newbal' => $newBalance,
            'userId' => $this->getKey(),
        ];

        // Callers (e.g. the Billstack funding webhook) may pin the ledger row's
        // id to an external reference so retries stay idempotent.
        if (! empty($meta['id'])) {
            $attributes['id'] = $meta['id'];
        }

        return WalletHistory::create($attributes);
    }
}
