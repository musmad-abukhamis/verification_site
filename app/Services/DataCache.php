<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\NetworkPrefix;
use App\Models\Plan;
use Illuminate\Support\Facades\Cache;

/**
 * Cached, role-neutral catalog data for the buy-data hot path. Role pricing is
 * applied per-request from the cached base rows so the page needs ~1 uncached
 * (user-specific) query. Admin writes call flush().
 */
class DataCache
{
    private const TTL = 3600;

    private const CATALOG = 'data.catalog';

    private const PREFIX_MAP = 'data.prefix_map';

    /**
     * Role-neutral visible plan rows (all price columns retained for re-pricing).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function catalog(): array
    {
        return Cache::remember(self::CATALOG, self::TTL, fn () => Plan::where('plan_status', 'on')
            ->orderBy('network')
            ->orderBy('type')
            ->orderBy('price')
            ->get(['id', 'code', 'network', 'type', 'name', 'price', 'agent_price', 'api_price', 'validity', 'status'])
            ->map(fn (Plan $p) => [
                // The public plan id integrators quote; the internal key is
                // deliberately not published.
                'id' => $p->code,
                'code' => $p->code,
                'network' => $p->network,
                'type' => $p->type,
                'name' => $p->name,
                'price' => (float) $p->price,
                'agent_price' => (float) $p->agent_price,
                'api_price' => (float) $p->api_price,
                'validity' => $p->validity,
                'available' => $p->status === 'on',
            ])
            ->all());
    }

    /**
     * Catalog priced for a role, with the other price columns stripped.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function catalogForRole(?UserRole $role): array
    {
        return array_map(function (array $p) use ($role) {
            $price = match ($role) {
                UserRole::AGENT, UserRole::SMART => $p['agent_price'] ?: $p['price'],
                UserRole::API => $p['api_price'] ?: $p['price'],
                default => $p['price'],
            };

            return [
                'id' => $p['id'],
                'network' => $p['network'],
                'type' => $p['type'],
                'name' => $p['name'],
                'price' => (float) $price,
                'validity' => $p['validity'],
                'available' => $p['available'],
            ];
        }, self::catalog());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function prefixMap(): array
    {
        return Cache::remember(self::PREFIX_MAP, self::TTL, fn () => NetworkPrefix::map());
    }

    public static function flush(): void
    {
        Cache::forget(self::CATALOG);
        Cache::forget(self::PREFIX_MAP);
    }
}
