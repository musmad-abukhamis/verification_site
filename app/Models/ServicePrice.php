<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Per-service, per-role pricing -- the single source of truth for what a NIN
 * service or slip download costs.
 *
 * One row per (service, role). The `DEFAULT` role is the base price everyone
 * pays; a UserRole row overrides it for that role only, the same idea as
 * Plan::priceForRole in the data module.
 */
class ServicePrice extends Model
{
    use HasPrismaId;

    /** The sentinel `role` of the base price. Not a UserRole. */
    public const BASE = 'DEFAULT';

    public const CACHE_KEY = 'service_prices.all';

    protected $guarded = [];

    protected $casts = [
        'price' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * The services that can be priced, in display order.
     *
     * Adding an entry here is all it takes for it to appear in
     * Admin > Service Prices; nothing else enumerates them.
     */
    public const SERVICES = [
        'nin.verify' => ['label' => 'NIN Verification', 'group' => 'verification'],
        'nin.phone' => ['label' => 'Phone Verification', 'group' => 'verification'],
        'nin.demographic' => ['label' => 'Demographic Verification', 'group' => 'verification'],
        'nin.ipe' => ['label' => 'IPE Clearance', 'group' => 'verification'],
        'nin.validation' => ['label' => 'NIN Validation', 'group' => 'verification'],
        'slip.regular' => ['label' => 'Regular Slip', 'group' => 'slip'],
        'slip.standard' => ['label' => 'Standard Slip', 'group' => 'slip'],
        'slip.premium' => ['label' => 'Premium Slip', 'group' => 'slip'],
        'slip.nvs' => ['label' => 'NVS Slip', 'group' => 'slip'],
        'slip.advanced' => ['label' => 'Advanced Slip', 'group' => 'slip'],
    ];

    /**
     * Every row, indexed [service][role]. One query per request at most.
     *
     * @return array<string, array<string, self>>
     */
    public static function indexed(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $indexed = [];

            foreach (static::all() as $row) {
                $indexed[$row->service][$row->role] = $row;
            }

            return $indexed;
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * What this role pays for this service, or null when it is unavailable.
     *
     * Resolution order:
     *   1. The base row decides whether the service runs at all. No base row,
     *      or an inactive one, means unavailable for everyone -- switching a
     *      service off must not be defeated by a leftover role override.
     *   2. An active row for the caller's role overrides the base price.
     *   3. Otherwise the base price applies.
     *
     * Null is never a price. Callers must refuse the request rather than
     * substitute a default, or a user gets billed an amount that appears
     * nowhere in the admin.
     */
    public static function priceFor(string $service, ?UserRole $role = null): ?float
    {
        $rows = static::indexed()[$service] ?? [];
        $base = $rows[self::BASE] ?? null;

        if (! $base || ! $base->is_active) {
            return null;
        }

        $override = $role ? ($rows[$role->value] ?? null) : null;

        return $override && $override->is_active
            ? $override->price
            : $base->price;
    }

    /**
     * Convenience for the common case of pricing for the logged-in user.
     */
    public static function priceForUser(string $service, ?User $user): ?float
    {
        return static::priceFor($service, $user?->role);
    }

    public static function label(string $service): string
    {
        return self::SERVICES[$service]['label'] ?? $service;
    }
}
