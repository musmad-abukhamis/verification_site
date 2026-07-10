<?php

namespace App\Services\Nin;

use App\Services\Nin\Contracts\NinProvider;
use App\Services\Nin\Providers\ArewaSmartProvider;
use App\Services\Nin\Providers\PremblyProvider;
use App\Services\Nin\Providers\ProviderFiveProvider;
use App\Services\Nin\Providers\ProviderFourProvider;
use App\Services\Nin\Providers\ProviderThreeProvider;

/**
 * Central registry of NIN providers.
 *
 * The single place that knows the full provider list. Controllers resolve a
 * provider by key; the frontend gets its metadata from forFrontend().
 */
class NinProviderManager
{
    /** @var array<string, class-string<NinProvider>> */
    protected array $registry = [
        'prembly' => PremblyProvider::class,
        'arewasmart' => ArewaSmartProvider::class,
        'provider3' => ProviderThreeProvider::class,
        'provider4' => ProviderFourProvider::class,
        'provider5' => ProviderFiveProvider::class,
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
