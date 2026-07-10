<?php

namespace App\Services\Vendors;

use App\Models\DataTransaction;

/**
 * Token-style B: {network, mobile_number, plan, Ported_number} with an
 * `Authorization: Token {key}` header.
 *
 * `Ported_number` is hard-coded true for every purchase (matches current
 * production behaviour). The UI's ported flag only suppresses hints / annotates
 * records — it must NOT change vendor payloads.
 */
class TokenStyleBDriver extends AbstractHttpDriver
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
            ['Authorization' => 'Token '.($credentials['key'] ?? '')],
            $this->describePayload($txn, $externalPlanId, $externalNetworkCode),
        );
    }

    public function requery(DataTransaction $txn, string $baseUrl, array $credentials): VendorResult
    {
        return $this->post(
            rtrim($baseUrl, '/').'/status',
            ['Authorization' => 'Token '.($credentials['key'] ?? '')],
            ['request-id' => $txn->getKey()],
        );
    }

    public function describePayload(DataTransaction $txn, string $externalPlanId, string $externalNetworkCode): array
    {
        return [
            'network' => $externalNetworkCode,
            'mobile_number' => $txn->phone,
            'plan' => $externalPlanId,
            'Ported_number' => true,
        ];
    }
}
