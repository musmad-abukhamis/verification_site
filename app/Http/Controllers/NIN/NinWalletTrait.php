<?php

namespace App\Http\Controllers\NIN;

use App\Models\NinServicePrice;
use App\Models\User;
use App\Models\VerifyApiConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Pricing for the NIN section.
 *
 * Service fees come from ninServicePrices -- the table Admin > Service Prices
 * edits. Slip *downloads* are priced per slip type on verifyapiconfiq, which is
 * what the Slip Types panel on that same page writes.
 *
 * Service prices return null when unconfigured so the caller can refuse the
 * request; they must never fall back to a hardcoded amount, or the user gets
 * billed a figure that appears nowhere in the admin.
 */
trait NinWalletTrait
{
    /**
     * The single-row slip pricing config (Prisma verifyapiconfiq).
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
     * NIN verification price. Both provider versions share one fee, so v1 and
     * v2 alike read searchslip1.
     */
    protected function getVerificationPrice(): ?float
    {
        return NinServicePrice::priceFor('searchslip1');
    }

    // Phone and demographic verification are priced here too, but nothing in
    // this trait reads them: those methods run through the provider selector
    // (AbstractNinProvider::priceFor) and the reseller API
    // (NinVerificationService), which read phone_verify / demo_verify directly.

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
    protected function getIpePrice(): ?float
    {
        return NinServicePrice::priceFor('ipe');
    }

    /**
     * NIN validation price.
     */
    protected function getValidationPrice(): ?float
    {
        return NinServicePrice::priceFor('validation');
    }

    /**
     * The response for a service with no price. That means either no admin has
     * set one yet, or one switched the service off in Admin > Service Prices --
     * both are stored as a NULL column. Refusing is the only safe option: we
     * cannot guess what to charge.
     */
    protected function unpricedService()
    {
        return back()->withErrors([
            'message' => 'This service is currently unavailable. Please contact support.',
        ]);
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
