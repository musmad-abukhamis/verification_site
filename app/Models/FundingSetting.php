<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row wallet-funding configuration (table "funding_settings").
 *
 * Follows the same convention as the other single-row config tables: a fixed
 * string id of 'API1'.
 */
class FundingSetting extends Model
{
    protected $table = 'funding_settings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credit_net_of_fees' => 'boolean',
        ];
    }

    /**
     * The one row, created on first use so the admin screen and the webhook
     * never have to cope with its absence.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 'API1']);
    }

    /**
     * Should a funding payment credit the settlement amount rather than the
     * gross amount the customer sent?
     */
    public static function creditsNetOfFees(): bool
    {
        return (bool) static::current()->credit_net_of_fees;
    }
}
