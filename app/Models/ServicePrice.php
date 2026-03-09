<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ServicePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_type',
        'name',
        'description',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get price for a specific service type
     */
    public static function getPrice(string $serviceType, float $default = 0): float
    {
        return Cache::remember("service_price.{$serviceType}", 3600, function () use ($serviceType, $default) {
            $service = static::where('service_type', $serviceType)
                ->where('is_active', true)
                ->first();

            return $service ? (float) $service->price : $default;
        });
    }

    /**
     * Get all active service prices
     */
    public static function getAllActive(): array
    {
        return Cache::remember('service_prices.all_active', 3600, function () {
            return static::where('is_active', true)
                ->pluck('price', 'service_type')
                ->toArray();
        });
    }

    /**
     * Get all active services with full details
     */
    public static function getActiveServices(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember('service_prices.active_services', 3600, function () {
            return static::where('is_active', true)->get();
        });
    }

    /**
     * Clear the price cache
     */
    public static function clearCache(): void
    {
        Cache::forget('service_prices.all_active');
        Cache::forget('service_prices.active_services');
        
        // Clear individual service caches
        $services = static::all();
        foreach ($services as $service) {
            Cache::forget("service_price.{$service->service_type}");
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
