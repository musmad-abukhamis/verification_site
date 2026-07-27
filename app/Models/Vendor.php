<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An upstream data vendor. Credentials are stored as an encrypted JSON blob
 * ({key} for token drivers, or {client_id, client_secret, token_url} for oauth).
 */
class Vendor extends Model
{
    use HasPrismaId;

    protected $fillable = [
        'name', 'base_url', 'driver', 'credentials', 'is_active', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(VendorRoute::class, 'vendor_id');
    }

    public function planMappings(): HasMany
    {
        return $this->hasMany(PlanVendorMapping::class, 'vendor_id');
    }

    public function networkMappings(): HasMany
    {
        return $this->hasMany(NetworkVendorMapping::class, 'vendor_id');
    }
}
