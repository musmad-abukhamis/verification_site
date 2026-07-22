<?php

namespace Tests\Concerns;

use App\Models\VerificationEndpoint;
use App\Models\VerificationProvider;
use App\Models\VerificationRoute;
use App\Services\Verification\ServiceCatalog;

/**
 * Test helper for the config-driven provider engine.
 *
 * Verification no longer has hardcoded providers, so any test that exercises a
 * NIN/BVN lookup has to put at least one provider in the routing chain first --
 * otherwise the request is (correctly) refused with "No verification provider is
 * configured".
 */
trait ConfiguresVerificationProviders
{
    /**
     * Create an active provider routed at position 1 for the given services.
     *
     * The field map is deliberately empty: canonical inputs pass through under
     * their own names, which is all a fake HTTP response needs.
     *
     * @param  array<int, string>  $services
     */
    protected function routeProviderFor(
        array $services,
        string $slug = 'testprovider',
        string $baseUrl = 'https://provider.test',
    ): VerificationProvider {
        $provider = VerificationProvider::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => ucfirst($slug),
                'base_url' => $baseUrl,
                'auth_type' => 'bearer',
                'credentials' => ['token' => 'test-token'],
                'is_active' => true,
                'priority' => 10,
                'timeout_seconds' => 30,
            ],
        );

        foreach ($services as $service) {
            if (! ServiceCatalog::has($service)) {
                throw new \InvalidArgumentException("Unknown verification service: {$service}");
            }

            VerificationEndpoint::updateOrCreate(
                ['provider_id' => $provider->getKey(), 'service' => $service],
                [
                    'http_method' => 'POST',
                    'path' => '/'.str_replace('.', '/', $service),
                    'body_type' => 'json',
                    'is_active' => true,
                ],
            );

            // Append rather than overwrite, so calling this twice builds a
            // two-provider failover chain in the order the test created them.
            $existing = VerificationRoute::where('service', $service)
                ->where('provider_id', $provider->getKey())
                ->exists();

            if (! $existing) {
                VerificationRoute::create([
                    'service' => $service,
                    'provider_id' => $provider->getKey(),
                    'position' => VerificationRoute::where('service', $service)->max('position') + 1,
                ]);
            }
        }

        return $provider->refresh();
    }

    /**
     * Route a provider for every NIN verification method.
     */
    protected function routeNinProvider(): VerificationProvider
    {
        return $this->routeProviderFor(['nin.verify', 'nin.phone', 'nin.demographic']);
    }
}
