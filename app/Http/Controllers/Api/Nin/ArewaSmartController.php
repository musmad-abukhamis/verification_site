<?php

namespace App\Http\Controllers\Api\Nin;

/** NIN verification endpoint dedicated to the ArewaSmart provider. */
class ArewaSmartController extends AbstractProviderController
{
    protected function providerKey(): string
    {
        return 'arewasmart';
    }
}
