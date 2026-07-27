<?php

namespace App\Services\Vendors;

use App\Models\DataTransaction;

/**
 * Token-style A (e.g. bozavtu): {network, phone, bypass, data_plan, request-id}
 * with an `Authorization: {scheme} {key}` header, where scheme is the vendor's
 * configured Token (default) or Bearer.
 */
class TokenStyleADriver extends AbstractHttpDriver
{
    public function purchase(
        DataTransaction $txn,
        string $externalPlanId,
        string $externalNetworkCode,
        string $baseUrl,
        array $credentials,
    ): VendorResult {
        return $this->post(
            $baseUrl,
            $this->authHeader($credentials),
            $this->describePayload($txn, $externalPlanId, $externalNetworkCode),
        );
    }

    public function requery(DataTransaction $txn, string $baseUrl, array $credentials): VendorResult
    {
        // No dedicated requery contract for this vendor shape; a bare GET on the
        // base URL with the reference is the best-effort probe. Anything other
        // than an explicit success stays ambiguous.
        return $this->post(
            rtrim($baseUrl, '/').'/status',
            $this->authHeader($credentials),
            ['request-id' => $txn->getKey()],
            errorResponseIsFail: false,
        );
    }

    public function describePayload(DataTransaction $txn, string $externalPlanId, string $externalNetworkCode): array
    {
        return [
            'network' => $externalNetworkCode,
            'phone' => $txn->phone,
            'bypass' => true,
            'data_plan' => $externalPlanId,
            'request-id' => $txn->getKey(),
        ];
    }
}
