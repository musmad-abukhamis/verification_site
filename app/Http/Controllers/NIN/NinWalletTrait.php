<?php

namespace App\Http\Controllers\NIN;

use App\Models\Wallet;

trait NinWalletTrait
{
    protected function getPrice(string $slipType): float
    {
        $prices = config('services.nin.prices', []);
        return match ($slipType) {
            'premium'  => (float) ($prices['premium'] ?? 150),
            'standard' => (float) ($prices['standard'] ?? 100),
            default    => (float) ($prices['regular'] ?? 50),
        };
    }

    protected function getPremiumPrice(): float
    {
        return (float) config('services.nin.prices.premium', 150);
    }

    protected function getIpePrice(): float
    {
        return (float) config('services.nin.prices.ipe', 50);
    }

    protected function debitWallet(Wallet $wallet, float $amount): void
    {
        if ($wallet->bonus_balance >= $amount) {
            $wallet->bonus_balance -= $amount;
        } else {
            $remaining = $amount - $wallet->bonus_balance;
            $wallet->bonus_balance = 0;
            $wallet->balance -= $remaining;
        }
        $wallet->save();
    }

    protected function creditWallet(Wallet $wallet, float $amount): void
    {
        $wallet->refresh();
        $wallet->balance += $amount;
        $wallet->save();
    }
}
