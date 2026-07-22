<?php

namespace App\Services\Verification;

/**
 * Normalized outcome of a verification call — one provider's, or the chain's.
 *
 * - success: the provider returned the record.
 * - fail:    the provider explicitly answered "no" (not found, bad parameter,
 *            insufficient upstream balance). Safe to try the next provider.
 * - timeout: ambiguous — connection error, non-JSON reply, HTTP 5xx. Whether
 *            the chain may continue depends on the service: a lookup can be
 *            retried, a submission (BVN retrieval, IPE) must not be.
 *
 * Deliberately mirrors App\Services\Vendors\VendorResult so the two modules
 * read the same way.
 */
class VerificationOutcome
{
    /**
     * @param  array<string, mixed>  $data  canonical identity fields
     * @param  array<string, mixed>  $raw   the provider's untouched response
     * @param  array<int, array<string, mixed>>  $attempts  one entry per hop
     */
    public function __construct(
        public readonly string $outcome, // success | fail | timeout
        public readonly array $data = [],
        public readonly ?string $message = null,
        public readonly ?string $reference = null,
        public readonly array $raw = [],
        public readonly ?string $providerId = null,
        public readonly ?string $providerName = null,
        public readonly ?int $httpStatus = null,
        public readonly array $attempts = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $raw
     */
    public static function success(
        array $data,
        array $raw = [],
        ?string $message = null,
        ?string $reference = null,
        ?string $providerId = null,
        ?string $providerName = null,
        ?int $httpStatus = 200,
    ): self {
        return new self('success', $data, $message, $reference, $raw, $providerId, $providerName, $httpStatus);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fail(
        ?string $message,
        array $raw = [],
        ?string $providerId = null,
        ?string $providerName = null,
        ?int $httpStatus = null,
    ): self {
        return new self('fail', [], $message, null, $raw, $providerId, $providerName, $httpStatus);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function timeout(
        ?string $message,
        array $raw = [],
        ?string $providerId = null,
        ?string $providerName = null,
        ?int $httpStatus = null,
    ): self {
        return new self('timeout', [], $message, null, $raw, $providerId, $providerName, $httpStatus);
    }

    public function isSuccess(): bool
    {
        return $this->outcome === 'success';
    }

    public function isFail(): bool
    {
        return $this->outcome === 'fail';
    }

    public function isTimeout(): bool
    {
        return $this->outcome === 'timeout';
    }

    /**
     * The same outcome with the chain's per-hop trace attached.
     *
     * @param  array<int, array<string, mixed>>  $attempts
     */
    public function withAttempts(array $attempts): self
    {
        return new self(
            $this->outcome,
            $this->data,
            $this->message,
            $this->reference,
            $this->raw,
            $this->providerId,
            $this->providerName,
            $this->httpStatus,
            $attempts,
        );
    }
}
