<?php

namespace App\Services\Vendors;

use App\Models\DataTransaction;

/**
 * Each driver knows how to talk to one vendor API shape. Drivers are pure
 * request/response translators — they never touch the wallet or persist state;
 * VendorDispatcher owns credentials, attempt logging and payload sanitising.
 */
interface VendorDriverInterface
{
    /**
     * Attempt to fulfil the purchase.
     *
     * @param  array<string, mixed>  $credentials  decrypted vendor credentials
     */
    public function purchase(
        DataTransaction $txn,
        string $externalPlanId,
        string $externalNetworkCode,
        string $baseUrl,
        array $credentials,
    ): VendorResult;

    /**
     * Ask the vendor about a previously-submitted purchase (reconciliation).
     *
     * @param  array<string, mixed>  $credentials
     */
    public function requery(
        DataTransaction $txn,
        string $baseUrl,
        array $credentials,
    ): VendorResult;

    /**
     * The request body this driver would send — with credentials already
     * excluded — so the dispatcher can store a safe audit payload.
     *
     * @return array<string, mixed>
     */
    public function describePayload(
        DataTransaction $txn,
        string $externalPlanId,
        string $externalNetworkCode,
    ): array;
}
