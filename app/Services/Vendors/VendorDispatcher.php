<?php

namespace App\Services\Vendors;

use App\Models\DataTransaction;
use App\Models\DataTransactionAttempt;
use App\Models\Vendor;
use InvalidArgumentException;

/**
 * Resolves the concrete driver for a vendor, runs the call, and records a
 * sanitised data_transaction_attempts row for every hop. Credentials never
 * enter the stored payload (drivers' describePayload() excludes them).
 */
class VendorDispatcher
{
    /**
     * @var array<string, class-string<VendorDriverInterface>>
     */
    protected array $drivers = [
        'token_style_a' => TokenStyleADriver::class,
        'token_style_b' => TokenStyleBDriver::class,
        'oauth' => OAuthDriver::class,
    ];

    public function driverFor(Vendor $vendor): VendorDriverInterface
    {
        $class = $this->drivers[$vendor->driver] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unknown vendor driver: {$vendor->driver}");
        }

        return app($class);
    }

    public function purchase(
        DataTransaction $txn,
        Vendor $vendor,
        string $externalPlanId,
        string $externalNetworkCode,
    ): VendorResult {
        $driver = $this->driverFor($vendor);

        $result = $driver->purchase(
            $txn,
            $externalPlanId,
            $externalNetworkCode,
            $vendor->base_url,
            (array) $vendor->credentials,
        );

        $this->logAttempt(
            $txn,
            $vendor,
            $driver->describePayload($txn, $externalPlanId, $externalNetworkCode),
            $result,
        );

        return $result;
    }

    public function requery(DataTransaction $txn, Vendor $vendor): VendorResult
    {
        $driver = $this->driverFor($vendor);

        $result = $driver->requery($txn, $vendor->base_url, (array) $vendor->credentials);

        $this->logAttempt($txn, $vendor, ['requery' => $txn->getKey()], $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $requestPayload  already credential-free
     */
    private function logAttempt(DataTransaction $txn, Vendor $vendor, array $requestPayload, VendorResult $result): void
    {
        DataTransactionAttempt::create([
            'data_transaction_id' => $txn->getKey(),
            'vendor_id' => $vendor->getKey(),
            'request_payload' => $requestPayload,
            'response' => $result->raw,
            'outcome' => $result->outcome,
        ]);
    }
}
