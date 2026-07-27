<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Api\Nin\AbstractProviderController;
use Illuminate\Http\Request;

/**
 * NIN verification for API resellers: one endpoint.
 *
 * Deliberately a subclass rather than a reimplementation -- the charge, refund,
 * logging and JSON envelope are the flow the web app already uses, and pricing
 * is role-aware, so a reseller is billed their own rate without any of it being
 * duplicated here.
 *
 * A `provider` field in the body is accepted and ignored: the upstream provider
 * comes from the routing chain in Admin > Verification. Honouring the caller's
 * choice would let an integrator pin themselves to one provider and lose
 * failover, and the per-provider endpoints it referred to no longer exist.
 */
class NinController extends AbstractProviderController
{
    protected function providerKey(): string
    {
        return 'auto';
    }

    /**
     * Kept so existing integrations that call it keep working. It now reports
     * the single routed entry rather than a menu to choose from.
     */
    public function providers(Request $request)
    {
        $providers = array_map(fn ($provider) => [
            'key' => $provider->key(),
            'label' => $provider->label(),
            'methods' => $provider->supportedMethods(),
        ], $this->providers->active());

        return response()->json([
            'status' => 'success',
            'data' => ['providers' => array_values($providers)],
        ]);
    }
}
