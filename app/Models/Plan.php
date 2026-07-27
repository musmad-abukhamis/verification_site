<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/**
 * A data bundle. Integer PK. Per-vendor external plan codes live in
 * plan_vendor_mappings, not on this row.
 *
 * `status` is type-level availability (a whole data type can be switched off);
 * `plan_status` is this plan's own visibility.
 */
class Plan extends Model
{
    /** The public plan id is a 1-3 digit number. */
    public const MAX_CODE = 999;

    protected $fillable = [
        'code', 'network', 'type', 'name', 'price', 'agent_price', 'api_price',
        'validity', 'status', 'plan_status',
    ];

    protected function casts(): array
    {
        return [
            'code' => 'integer',
            'price' => 'float',
            'agent_price' => 'float',
            'api_price' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Plan $plan) {
            if (empty($plan->code)) {
                $plan->code = static::allocateCode();
            }

            // Covers admin-chosen codes too, so a manually set 250 is never
            // handed out again automatically after that plan is deleted.
            static::rememberIssued((int) $plan->code);
        });
    }

    private static function rememberIssued(int $code): void
    {
        if ($code > (int) DataSetting::get(self::HIGH_WATER, 0)) {
            DataSetting::put(self::HIGH_WATER, $code);
        }
    }

    /** Highest code ever issued, including codes since deleted. */
    private const HIGH_WATER = 'plan_code_high_water';

    /**
     * The next public plan id.
     *
     * Counts up from the highest code *ever issued* rather than the highest in
     * use, because a code is quoted to external integrators and stored in their
     * systems: handing a deleted plan's number to a new plan would silently
     * start selling them a different bundle. MAX(code) alone is not enough --
     * delete the newest plan and it would hand the same number straight back.
     *
     * Gaps are only reused once the 3-digit range is genuinely exhausted.
     */
    public static function allocateCode(): int
    {
        $highWater = max(
            (int) DataSetting::get(self::HIGH_WATER, 0),
            (int) static::max('code'),
        );

        $next = $highWater + 1;

        if ($next <= self::MAX_CODE) {
            return $next;
        }

        $taken = static::pluck('code')->all();

        for ($candidate = 1; $candidate <= self::MAX_CODE; $candidate++) {
            if (! in_array($candidate, $taken, true)) {
                Log::warning('Plan codes exhausted; reusing a retired code', ['code' => $candidate]);

                return $candidate;
            }
        }

        throw new \RuntimeException('No plan code is available: all '.self::MAX_CODE.' are in use.');
    }

    /**
     * Look a plan up by the id external callers quote.
     */
    public function scopeByCode(Builder $query, int|string $code): Builder
    {
        return $query->where('code', (int) $code);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'on')->where('plan_status', 'on');
    }

    public function scopeByNetwork(Builder $query, string $network): Builder
    {
        return $query->where('network', strtolower($network));
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function vendorMappings(): HasMany
    {
        return $this->hasMany(PlanVendorMapping::class, 'plan_id');
    }

    /**
     * Authoritative, role-adjusted price. Never trust a client-supplied price.
     */
    public function priceForRole(?UserRole $role): float
    {
        return match ($role) {
            UserRole::AGENT, UserRole::SMART => (float) ($this->agent_price ?: $this->price),
            UserRole::API => (float) ($this->api_price ?: $this->price),
            default => (float) $this->price,
        };
    }

    public function priceForUser(?User $user): float
    {
        return $this->priceForRole($user?->role);
    }
}
