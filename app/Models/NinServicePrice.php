<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Prisma model: ninServicePrices (single-row config, string id defaulting "API1").
 *
 * This is the row Admin > Service Prices edits, and the single source of truth
 * for what every NIN service costs. Slip *downloads* are the one exception:
 * they stay priced per slip type on verifyapiconfiq.
 */
class NinServicePrice extends Model
{
    public const CACHE_KEY = 'ninServicePrices.API1';

    protected $table = 'ninServicePrices';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $attributes = [
        'id' => 'API1',
    ];

    protected $guarded = [];

    public static function current(): self
    {
        return Cache::remember(self::CACHE_KEY, 300, fn () => static::firstOrCreate(['id' => 'API1']));
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The configured price for a column, or null when no admin has set one.
     *
     * Every column in this table is a string in the ported schema, so "" and
     * non-numeric junk count as unset. Returning null rather than a hardcoded
     * fallback is deliberate: a fallback bills the user an amount that appears
     * nowhere in Admin > Service Prices, which is exactly the behaviour this
     * change exists to remove.
     */
    public static function priceFor(string $column): ?float
    {
        $value = static::current()->{$column} ?? null;

        return ($value === null || $value === '' || ! is_numeric($value)) ? null : (float) $value;
    }
}
