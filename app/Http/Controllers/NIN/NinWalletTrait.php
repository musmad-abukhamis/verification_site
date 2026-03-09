<?php

namespace App\Http\Controllers\NIN;

use App\Models\ServicePrice;
use App\Models\SlipType;
use App\Models\Wallet;

trait NinWalletTrait
{
    /**
     * Get the NIN verification price from database
     */
    protected function getVerificationPrice(): float
    {
        return ServicePrice::getPrice('nin_verification', 50);
    }

    /**
     * Get slip price by type from database
     */
    protected function getSlipPrice(string $slipType): float
    {
        return SlipType::getPrice($slipType, 100);
    }

    /**
     * Get all active slip types
     */
    protected function getActiveSlipTypes(): array
    {
        return SlipType::getForFrontend();
    }

    /**
     * Legacy method - get price by slip type name
     * @deprecated Use getSlipPrice() instead
     */
    protected function getPrice(string $slipType): float
    {
        return $this->getSlipPrice($slipType);
    }

    /**
     * Legacy method - get premium price
     * @deprecated Use getSlipPrice('premium') instead
     */
    protected function getPremiumPrice(): float
    {
        return $this->getSlipPrice('premium');
    }

    /**
     * Get IPE submission price from database
     */
    protected function getIpePrice(): float
    {
        return ServicePrice::getPrice('nin_ipe_submission', 50);
    }

    /**
     * Debit wallet (bonus first, then main balance)
     */
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

    /**
     * Credit wallet (refund)
     */
    protected function creditWallet(Wallet $wallet, float $amount): void
    {
        $wallet->refresh();
        $wallet->balance += $amount;
        $wallet->save();
    }
}
