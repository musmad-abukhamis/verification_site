<?php

namespace App\Http\Controllers\NIN;

use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Pricing for the NIN section.
 *
 * Everything -- service fees and slip downloads alike -- comes from
 * service_prices, which Admin > Service Prices edits. Prices are role-aware:
 * pass the user being charged, or leave it out to price for whoever is logged
 * in.
 *
 * Prices return null when the service is unpriced or switched off, so the
 * caller can refuse the request; they must never fall back to a hardcoded
 * amount, or the user gets billed a figure that appears nowhere in the admin.
 */
trait NinWalletTrait
{
    /**
     * Map a slip-type code to its service key.
     */
    protected function slipService(string $slipType): string
    {
        return 'slip.'.match (strtolower($slipType)) {
            'regular', 'reg', 'regslip' => 'regular',
            'premium' => 'premium',
            'nvs' => 'nvs',
            'advanced', 'adv' => 'advanced',
            default => 'standard',
        };
    }

    /**
     * NIN verification price. Both provider versions share one fee.
     */
    protected function getVerificationPrice(?User $user = null): ?float
    {
        return ServicePrice::priceForUser('nin.verify', $user ?? Auth::user());
    }

    /**
     * Slip price by type.
     */
    protected function getSlipPrice(string $slipType, ?User $user = null): ?float
    {
        return ServicePrice::priceForUser($this->slipService($slipType), $user ?? Auth::user());
    }

    /**
     * Slip types the user can actually buy, at the price they would pay.
     */
    protected function getActiveSlipTypes(?User $user = null): array
    {
        $types = [
            ['code' => 'regular', 'name' => 'Regular Slip', 'component_name' => 'RegularSlip'],
            ['code' => 'standard', 'name' => 'Standard Slip', 'component_name' => 'StandardSlip'],
            ['code' => 'premium', 'name' => 'Premium Slip', 'component_name' => 'PremiumSlip'],
            ['code' => 'nvs', 'name' => 'NVS Slip', 'component_name' => 'NvsSlip'],
            ['code' => 'advanced', 'name' => 'Advanced Slip', 'component_name' => 'AdvancedSlip'],
        ];

        $user ??= Auth::user();

        return array_values(array_filter(array_map(function (array $type) use ($user) {
            $price = $this->getSlipPrice($type['code'], $user);

            return $price !== null ? [...$type, 'price' => $price] : null;
        }, $types)));
    }

    /**
     * IPE submission price.
     */
    protected function getIpePrice(?User $user = null): ?float
    {
        return ServicePrice::priceForUser('nin.ipe', $user ?? Auth::user());
    }

    /**
     * NIN validation price.
     */
    protected function getValidationPrice(?User $user = null): ?float
    {
        return ServicePrice::priceForUser('nin.validation', $user ?? Auth::user());
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
