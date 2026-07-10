<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ordered vendor priority for a (network, type). Position 1 is the primary
 * vendor; positions 2+ are failover candidates.
 */
class VendorRoute extends Model
{
    protected $fillable = ['network', 'type', 'vendor_id', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function scopeForRoute(Builder $query, string $network, string $type): Builder
    {
        return $query->where('network', $network)->where('type', $type)->orderBy('position');
    }
}
