<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ordered provider priority for one service. Position 1 is the primary
 * provider; positions 2+ are failover candidates — the same contract as
 * VendorRoute in the data module.
 */
class VerificationRoute extends Model
{
    protected $fillable = ['service', 'provider_id', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(VerificationProvider::class, 'provider_id');
    }

    public function scopeForService(Builder $query, string $service): Builder
    {
        return $query->where('service', $service)->orderBy('position');
    }
}
