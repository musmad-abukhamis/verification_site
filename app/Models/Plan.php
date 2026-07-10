<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A data bundle. Integer PK. Per-vendor external plan codes live in
 * plan_vendor_mappings, not on this row.
 *
 * `status` is type-level availability (a whole data type can be switched off);
 * `plan_status` is this plan's own visibility.
 */
class Plan extends Model
{
    protected $fillable = [
        'network', 'type', 'name', 'price', 'agent_price', 'api_price',
        'validity', 'status', 'plan_status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'agent_price' => 'float',
            'api_price' => 'float',
        ];
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
