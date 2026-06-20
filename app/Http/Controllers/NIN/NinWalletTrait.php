<?php

namespace App\Http\Controllers\NIN;

use App\Models\User;
use App\Models\VerifyApiConfig;
use Illuminate\Support\Facades\Cache;

trait NinWalletTrait
{
    /**
     * The single-row NIN/verification pricing config (Prisma verifyapiconfiq).
     */
    protected function verifyConfig(): VerifyApiConfig
    {
        return Cache::remember('verifyapiconfiq.API1', 300, function () {
            return VerifyApiConfig::firstOrCreate(['id' => 'API1']);
        });
    }

    /**
     * Map a slip-type code to its verifyapiconfiq price column.
     */
    protected function slipPriceColumn(string $slipType): string
    {
        return match (strtolower($slipType)) {
            'regular', 'reg', 'regslip' => 'regslipprice',
            'premium' => 'premiumslipprice',
            'nvs' => 'nvsslipprice',
            'advanced', 'adv' => 'advslipprice',
            default => 'standardslipsprice',
        };
    }

    /**
     * NIN verification (pull) price.
     */
    protected function getVerificationPrice(): float
    {
        return (float) ($this->verifyConfig()->pullingprice ?? 50);
    }

    /**
     * Slip price by type from the config row.
     */
    protected function getSlipPrice(string $slipType): float
    {
        $column = $this->slipPriceColumn($slipType);

        return (float) ($this->verifyConfig()->{$column} ?? 100);
    }

    /**
     * Active slip types for the frontend, priced from the config row.
     */
    protected function getActiveSlipTypes(): array
    {
        $types = [
            ['code' => 'regular', 'name' => 'Regular Slip', 'component_name' => 'RegularSlip'],
            ['code' => 'standard', 'name' => 'Standard Slip', 'component_name' => 'StandardSlip'],
            ['code' => 'premium', 'name' => 'Premium Slip', 'component_name' => 'PremiumSlip'],
            ['code' => 'nvs', 'name' => 'NVS Slip', 'component_name' => 'NvsSlip'],
            ['code' => 'advanced', 'name' => 'Advanced Slip', 'component_name' => 'AdvancedSlip'],
        ];

        return array_values(array_filter(array_map(function (array $type) {
            $price = (float) ($this->verifyConfig()->{$this->slipPriceColumn($type['code'])} ?? 0);

            return $price > 0 ? [...$type, 'price' => $price] : null;
        }, $types)));
    }

    /**
     * @deprecated Use getSlipPrice() instead.
     */
    protected function getPrice(string $slipType): float
    {
        return $this->getSlipPrice($slipType);
    }

    /**
     * @deprecated Use getSlipPrice('premium') instead.
     */
    protected function getPremiumPrice(): float
    {
        return $this->getSlipPrice('premium');
    }

    /**
     * IPE submission price.
     */
    protected function getIpePrice(): float
    {
        return (float) ($this->verifyConfig()->ipeprice ?? 50);
    }

    /**
     * NIN validation price.
     */
    protected function getValidationPrice(): float
    {
        return (float) ($this->verifyConfig()->validation ?? 50);
    }

    /**
     * Shape a Validation/Ipe record into the snake_case fields the NIN index
     * tables expect (created_at, old_balance, new_balance, ...).
     */
    protected function presentNinRecord($record): array
    {
        return [
            'id' => $record->id,
            'reference' => $record->id,
            'nin' => $record->nin,
            'status' => $record->status,
            'result' => $record->result,
            'comment' => $record->comment,
            'old_balance' => (float) $record->oldBal,
            'new_balance' => (float) $record->newBal,
            'created_at' => $record->createdAt,
        ];
    }

    /**
     * Debit the user's wallet (balance lives on the user now).
     */
    protected function debitWallet(User $user, float $amount, array $meta = []): void
    {
        $user->debit($amount, false, $meta);
    }

    /**
     * Credit (refund) the user's wallet.
     */
    protected function creditWallet(User $user, float $amount, array $meta = []): void
    {
        $user->credit($amount, false, $meta);
    }
}
