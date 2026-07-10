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
    ) {}

    public static function success(?string $reference, array $raw, ?string $message = null): self
    {
        return new self('success', $message, $reference, $raw);
    }

    public static function fail(?string $message, array $raw = []): self
    {
        return new self('fail', $message, null, $raw);
    }

    public static function timeout(?string $message, array $raw = []): self
    {
        return new self('timeout', $message, null, $raw);
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
