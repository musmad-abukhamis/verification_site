<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'network',
        'plan_type',
        'name',
        'data_volume',
        'price',
        'agent_price',
        'api_price',
        'validity_days',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'agent_price' => 'decimal:2',
        'api_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByNetwork($query, string $network)
    {
        return $query->where('network', strtolower($network));
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('plan_type', strtolower($type));
    }

    public function vendorPlans()
    {
        return $this->hasMany(VendorPlan::class, 'plan_id');
    }
}