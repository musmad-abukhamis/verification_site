<?php

namespace App\Services\Nin;

use App\Services\Nin\Contracts\NinProvider;
use App\Services\Nin\Providers\RoutedProvider;

/**
 * Central registry of NIN providers.
 *
 * The single place that knows the full provider list. Controllers resolve a
 * provider by key; the frontend gets its metadata from forFrontend().
 */
class NinProviderManager
{
    /**
     * One entry: the config-driven engine.
     *
     * The old V1..V5 entries were five hardcoded providers, and picking between
     * them was what the "version" selector on the verification pages did. That
     * choice now belongs to the routing chain in Admin > Verification, which
     * also gives failover the versioned providers never had — so exposing them
     * would just be a second, conflicting way to choose a provider.
     *
     * @var array<string, class-string<NinProvider>>
     */
    protected array $registry = [
        'auto' => RoutedProvider::class,
    ];

    /** @var array<string, NinProvider> */
    protected array $resolved = [];

    public function has(string $key): bool
    {
        return isset($this->registry[$key]);
    }

    public function get(string $key): ?NinProvider
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->resolved[$key] ??= app($this->registry[$key]);
    }

    /** @return NinProvider[] */
    public function all(): array
    {
        return array_map(fn ($key) => $this->get($key), array_keys($this->registry));
    }

    /** @return NinProvider[] */
    public function active(): array
    {
        return array_values(array_filter($this->all(), fn (NinProvider $p) => $p->isActive()));
    }

    /**
     * Method metadata consumed by the dynamic UI.
     */
    public static function methodCatalog(): array
    {
        return [
            ['value' => 'nin',         'label' => 'By NIN'],
            ['value' => 'phone',       'label' => 'By Phone Number'],
            ['value' => 'demographic', 'label' => 'By Demographic Information'],
        ];
    }

    /**
     * Shape providers + their methods/prices for the frontend selectors.
     */
    public function forFrontend(): array
    {
        return array_map(function (NinProvider $p) {
            $prices = [];
            foreach ($p->supportedMethods() as $method) {
                $prices[$method] = $p->priceFor($method);
            }

            return [
                'key' => $p->key(),
                'label' => $p->label(),
                'active' => $p->isActive(),
                'methods' => $p->supportedMethods(),
                'prices' => $prices,
            ];
        }, $this->active());
    }
}
