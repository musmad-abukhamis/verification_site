<?php

namespace App\Http\Controllers\Api\Nin;

/** NIN verification endpoint dedicated to Provider 5 (placeholder). */
class ProviderFiveController extends AbstractProviderController
{
    protected function providerKey(): string
    {
        return 'provider5';
    }
}
