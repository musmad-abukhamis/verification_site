<?php

namespace App\Services\Nin\Providers;

use App\Models\ServicePrice;
use App\Models\User;
use App\Services\Nin\Contracts\NinProvider;
use App\Services\Nin\VerificationResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared behaviour for all providers: config access, HTTP client setup,
 * timeout/exception handling and a consistent place to normalize errors.
 *
 * Concrete providers only implement the three verify* methods and their
 * own payload mapping.
 */
abstract class AbstractNinProvider implements NinProvider
{
    /** Config key under services.nin.providers.* and the provider's machine key. */
    abstract public function key(): string;

    protected function config(string $path, $default = null)
    {
        return config("services.nin.providers.{$this->key()}.{$path}", $default);
    }

    public function label(): string
    {
        return $this->config('label', ucfirst($this->key()));
    }

    public function isActive(): bool
    {
        return (bool) $this->config('active', false)
            && ! empty($this->config('base_url'));
    }

    public function supportedMethods(): array
    {
        return $this->config('methods', ['nin', 'phone', 'demographic']);
    }

    /**
     * Verification methods are priced once, in Admin > Service Prices, and every
     * provider charges the same fee for the same method -- but the fee depends
     * on the caller's role, so an AGENT or API reseller can pay a different
     * rate. They used to be priced per provider from
     * config('services.nin.providers.*.prices.*'), which meant changing a price
     * needed a deploy and the admin page had no effect.
     *
     * Null means unpriced or switched off -- callers must refuse rather than
     * invent a price.
     */
    public function priceFor(string $method, ?User $user = null): ?float
    {
        return ServicePrice::priceForUser(match ($method) {
            'phone' => 'nin.phone',
            'demographic' => 'nin.demographic',
            default => 'nin.verify',
        }, $user ?? Auth::user());
    }

    protected function baseUrl(): string
    {
        return rtrim((string) $this->config('base_url'), '/');
    }

    protected function apiKey(): ?string
    {
        return $this->config('api_key');
    }

    /**
     * Pre-configured HTTP client with auth + sane timeout for this provider.
     */
    protected function http(): PendingRequest
    {
        return Http::timeout(30)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey(),
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * Execute a request callback and translate transport-level failures
     * (timeouts, DNS, connection refused) into a normalized result.
     *
     * @param  callable():VerificationResult  $callback
     */
    protected function attempt(string $method, callable $callback): VerificationResult
    {
        if (! $this->isActive()) {
            return VerificationResult::failure(
                "The {$this->label()} provider is not configured.",
                'provider_unavailable',
                503,
            );
        }

        if (! in_array($method, $this->supportedMethods(), true)) {
            return VerificationResult::failure(
                "{$this->label()} does not support verification by {$method}.",
                'method_not_supported',
                422,
            );
        }

        try {
            return $callback();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning("[NIN][{$this->key()}] connection/timeout: ".$e->getMessage());

            return VerificationResult::failure(
                'The verification provider did not respond in time. Please try again.',
                'timeout',
                504,
            );
        } catch (\Throwable $e) {
            Log::error("[NIN][{$this->key()}] unexpected error: ".$e->getMessage());

            return VerificationResult::failure(
                'An unexpected error occurred while contacting the provider.',
                'provider_error',
                502,
            );
        }
    }

    /**
     * Extract a human-readable error message from a provider body.
     */
    protected function extractMessage(?array $body, string $fallback = 'Verification failed'): string
    {
        return $body['message']
            ?? $body['error']
            ?? $body['msg']
            ?? $body['detail']
            ?? $fallback;
    }
}
