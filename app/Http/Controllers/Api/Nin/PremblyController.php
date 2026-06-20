<?php

namespace App\Http\Controllers\Api\Nin;

/** NIN verification endpoint dedicated to the Prembly provider. */
class PremblyController extends AbstractProviderController
{
    protected function providerKey(): string
    {
        return 'prembly';
    }
}
