<?php

namespace App\Http\Controllers\Api\Nin;

/** NIN verification endpoint dedicated to Provider 4 (placeholder). */
class ProviderFourController extends AbstractProviderController
{
    protected function providerKey(): string
    {
        return 'provider4';
    }
}
