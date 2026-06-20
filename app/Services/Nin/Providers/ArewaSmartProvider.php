<?php

namespace App\Services\Nin\Providers;

use App\Services\Nin\VerificationResult;

/**
 * ArewaSmart (live).
 *
 * Differs from Prembly only on the by-NIN endpoint (verify_2). The phone and
 * demographic endpoints are shared by the upstream proxy, so those are
 * inherited unchanged from PremblyProvider.
 */
class ArewaSmartProvider extends PremblyProvider
{
    public function key(): string
    {
        return 'arewasmart';
    }

    public function verifyByNin(string $nin): VerificationResult
    {
        return $this->attempt('nin', function () use ($nin) {
            $response = $this->http()->post($this->baseUrl() . '/api/v1/nin/verify_2', [
                'idType'   => 'nin',
                'idValue'  => $nin,
                'slipType' => 'standard',
            ]);

            return $this->normalize($response, ['nin' => $nin]);
        });
    }
}
