<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: Plan (table "Plan", integer auto-increment id).
 *
 * Replaces the old DataPlan. Field map: plan_type → type, validity_days →
 * validity, is_active → status ('on'/'off'), agent_price → agentPrice,
 * api_price → apiPrice.
 */
class Plan extends Model
{
    protected $table = 'Plan';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'agentPrice' => 'integer',
            'apiPrice' => 'integer',
            'apiKey' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'on')->where('planStatus', 'on');
    }

    public function scopeByNetwork(Builder $query, string $network): Builder
    {
        return $query->where('network', strtolower($network));
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Resolve the price for a given user based on their role.
     */
    public function priceForUser(?User $user): int
    {
        $role = $user?->role;

        return match ($role) {
            UserRole::AGENT, UserRole::SMART => (int) ($this->agentPrice ?: $this->price),
            UserRole::API => (int) ($this->apiPrice ?: $this->price),
            default => (int) $this->price,
        };
    }
}
