<?php

namespace App\Services\Nin;

/**
 * Normalized result returned by every NIN provider.
 *
 * This guarantees a consistent shape regardless of which third-party API
 * was called, so controllers and the frontend never have to special-case
 * a provider's raw response.
 */
class VerificationResult
{
    public function __construct(
        public bool $success,
        public ?array $data = null,
        public ?string $message = null,
        public ?string $errorCode = null,
        public int $httpStatus = 200,
        public ?array $raw = null,
    ) {}

    public static function success(array $data, ?array $raw = null): self
    {
        return new self(success: true, data: $data, httpStatus: 200, raw: $raw ?? $data);
    }

    public static function failure(string $message, string $errorCode = 'verification_failed', int $httpStatus = 422, ?array $raw = null): self
    {
        return new self(
            success: false,
            message: $message,
            errorCode: $errorCode,
            httpStatus: $httpStatus,
            raw: $raw,
        );
    }
}
