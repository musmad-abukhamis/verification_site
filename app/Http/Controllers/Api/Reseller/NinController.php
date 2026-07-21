<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Api\Nin\AbstractProviderController;
use Illuminate\Http\Request;

/**
 * NIN verification for API resellers: one endpoint, provider chosen in the body.
 *
 * Deliberately a subclass rather than a reimplementation -- the charge, refund,
 * logging and JSON envelope are the flow the web app already uses, and pricing
 * is role-aware, so a reseller is billed their own rate without any of it being
 * duplicated here.
 *
 * The per-provider routes (/nin/providers/{provider}/verify) still exist for the
 * first-party UI; this is the same behaviour behind one URL.
 */
class NinController extends AbstractProviderController
{
    /** Fallback provider when the caller does not name one. */
    private const DEFAULT_PROVIDER = 'prembly';

    protected function providerKey(): string
    {
        $requested = request()->input('provider');

        return is_string($requested) && $requested !== ''
            ? $requested
            : self::DEFAULT_PROVIDER;
    }

    /**
     * Providers the caller can name, so an integrator does not have to guess.
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
