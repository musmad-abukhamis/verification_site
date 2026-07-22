<?php

namespace App\Http\Controllers\Api\Nin;

/**
 * NIN verification through the config-driven provider engine.
 *
 * Same contract as the per-provider endpoints, but the provider is chosen by
 * the routing configured in Admin > Verification rather than by the caller —
 * and a provider that declines hands off to the next in the chain.
 */
class RoutedController extends AbstractProviderController
{
    protected function providerKey(): string
    {
        return 'auto';
    }
}
