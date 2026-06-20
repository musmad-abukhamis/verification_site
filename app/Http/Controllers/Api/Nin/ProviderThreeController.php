<?php

namespace App\Http\Controllers\Api\Nin;

/** NIN verification endpoint dedicated to Provider 3 (placeholder). */
class ProviderThreeController extends AbstractProviderController
{
    protected function providerKey(): string
    {
        return 'provider3';
    }
}
