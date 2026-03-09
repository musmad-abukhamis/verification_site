<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SlipType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'price',
        'component_name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all active slip types ordered by sort_order
     */
    public static function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember('slip_types.active', 3600, function () {
            return static::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Find a slip type by its code
     */
    public static function findByCode(string $code): ?self
    {
        return Cache::remember("slip_type.{$code}", 3600, function () use ($code) {
            return static::where('code', $code)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * Get price for a specific slip type
     */
    public static function getPrice(string $code, float $default = 0): float
    {
        $slipType = static::findByCode($code);
        return $slipType ? (float) $slipType->price : $default;
    }

    /**
     * Get all slip types as array for frontend
     */
    public static function getForFrontend(): array
    {
        return static::getActive()->map(function ($slip) {
            return [
                'code' => $slip->code,
                'name' => $slip->name,
                'description' => $slip->description,
                'price' => (float) $slip->price,
                'component_name' => $slip->component_name,
            ];
        })->toArray();
    }

    /**
     * Clear the slip type cache
     */
    public static function clearCache(): void
    {
        Cache::forget('slip_types.active');
        
        $slipTypes = static::all();
        foreach ($slipTypes as $slipType) {
            Cache::forget("slip_type.{$slipType->code}");
        }
    }

    /**
     * Boot method to clear cache on model changes
     */
    protected static function booted(): void
    {
        static::saved(function () {
            static::clearCache();
        });

        static::deleted(function () {
            static::clearCache();
        });
    }
}
