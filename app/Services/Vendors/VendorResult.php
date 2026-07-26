<?php

namespace App\Services\Vendors;

/**
 * Normalized outcome of a single vendor call.
 *
 * - success: the vendor confirmed delivery.
 * - fail:    the vendor explicitly rejected the request (safe to fail over).
 * - timeout: ambiguous (network timeout / unexpected error) — NEVER fail over,
 *            it risks double delivery; leave the txn for reconciliation.
 */
class VendorResult
{
    public function __construct(
        public readonly string $outcome, // success | fail | timeout
        public readonly ?string $message = null,
        public readonly ?string $reference = null,
        public readonly array $raw = [],
        // Null when the call never produced a response (connection error, or a
        // token exchange that failed before the purchase was attempted).
        public readonly ?int $httpStatus = null,
        // False when the vendor may already have delivered despite answering
        // with an error (a 5xx). Such a call is still terminal -- the buyer gets
        // an answer -- but retrying it on another vendor could double-deliver.
        public readonly bool $failoverSafe = true,
    ) {}

    public static function success(?string $reference, array $raw, ?string $message = null, ?int $httpStatus = null): self
    {
        return new self('success', $message, $reference, $raw, $httpStatus);
    }

    public static function fail(?string $message, array $raw = [], ?int $httpStatus = null, bool $failoverSafe = true): self
    {
        return new self('fail', $message, null, $raw, $httpStatus, $failoverSafe);
    }

    /**
     * $reference is populated when the vendor accepted the order and told us
     * its own handle for it (an async "pending" reply). Reconciliation needs
     * that handle to ask what became of it.
     */
    public static function timeout(?string $message, array $raw = [], ?int $httpStatus = null, ?string $reference = null): self
    {
        return new self('timeout', $message, $reference, $raw, $httpStatus);
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
}
