<?php

namespace App\Services\Vendors;

use App\Models\DataTransaction;

/**
 * Another deployment of this same application, resold through its /api/v1/data
 * endpoint. Style-A body (its request normaliser accepts those aliases) with an
 * `Authorization: {scheme} {key}` header, where the key is an apitoken belonging
 * to a user with the API role on the upstream site.
 *
 * Two things make it its own driver rather than a variant of TokenStyleA:
 *
 *  - It is ASYNCHRONOUS. The reply carries `status: success` meaning "accepted"
 *    and the delivery state in `transaction_status` / `data.status`. The shared
 *    classifier reads the delivery state, so an accepted order correctly lands
 *    as pending rather than delivered.
 *  - Its status endpoint is a RESOURCE -- GET {base}/{reference} -- not the
 *    POST {base}/status the other drivers probe. Requerying the wrong URL is
 *    how a delivered purchase ends up refunded as unconfirmed.
 */
class ResellerDriver extends AbstractHttpDriver
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
        // The upstream reference, captured when it accepted the order. Without
        // it there is nothing to look up -- our own id means nothing there.
        $reference = $txn->vendor_reference;

        if (! $reference) {
            return VendorResult::timeout('No upstream reference recorded for this purchase');
        }

        return $this->get(
            rtrim($baseUrl, '/').'/'.rawurlencode($reference),
            $this->authHeader($credentials),
        );
    }

    public function describePayload(DataTransaction $txn, string $externalPlanId, string $externalNetworkCode): array
    {
        return [
            'network' => $externalNetworkCode,
            'phone' => $txn->phone,
            'bypass' => true,
            'data_plan' => $externalPlanId,
            // Echoed back verbatim as request-id, which makes the upstream
            // purchase idempotent against our reference: a retry of the same
            // order cannot double-charge.
            'request-id' => $txn->getKey(),
        ];
    }
}
