<?php

namespace App\Services\Nin\Contracts;

use App\Services\Nin\VerificationResult;

/**
 * Contract every NIN verification provider must implement.
 *
 * Inputs are always the standardized, already-validated values:
 *   - $nin   : exactly 11 digits
 *   - $phone : exactly 11 digits
 *   - demographic array: first_name, last_name, gender, date_of_birth (YYYY-MM-DD)
 *
 * Each implementation is responsible for mapping those into its own
 * provider-specific payload and normalizing the response into a
 * VerificationResult.
 */
interface NinProvider
{
    /** Machine key, e.g. "prembly". */
    public function key(): string;

    /** Human label for the UI. */
    public function label(): string;

    /** Whether the provider is configured and enabled. */
    public function isActive(): bool;

    /** Methods this provider supports: subset of ['nin','phone','demographic']. */
    public function supportedMethods(): array;

    /**
     * Price for a given method (nin|phone|demographic), from Admin > Service
     * Prices. Null when no price has been configured for it.
     */
    public function priceFor(string $method): ?float;

    public function verifyByNin(string $nin): VerificationResult;

    public function verifyByPhone(string $phone): VerificationResult;

    /** @param array{first_name:string,last_name:string,gender:string,date_of_birth:string} $demographic */
    public function verifyByDemographic(array $demographic): VerificationResult;
}
