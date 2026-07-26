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
        'reseller' => ResellerDriver::class,
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

        $startedAt = microtime(true);

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
            $startedAt,
        );

        return $result;
    }

    public function requery(DataTransaction $txn, Vendor $vendor): VendorResult
    {
        $driver = $this->driverFor($vendor);

        $startedAt = microtime(true);

        $result = $driver->requery($txn, $vendor->base_url, (array) $vendor->credentials);

        $this->logAttempt($txn, $vendor, ['requery' => $txn->getKey()], $result, $startedAt);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $requestPayload  already credential-free
     * @param  float  $startedAt  microtime(true) taken immediately before the call
     */
    private function logAttempt(
        DataTransaction $txn,
        Vendor $vendor,
        array $requestPayload,
        VendorResult $result,
        float $startedAt,
    ): void {
        DataTransactionAttempt::create([
            'data_transaction_id' => $txn->getKey(),
            'vendor_id' => $vendor->getKey(),
            // Denormalized so the audit still names the vendor after deletion.
            'vendor_name' => $vendor->name,
            'request_payload' => $requestPayload,
            'response' => $result->raw,
            'outcome' => $result->outcome,
            'http_status' => $result->httpStatus,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => $result->message,
        ]);
    }
}
