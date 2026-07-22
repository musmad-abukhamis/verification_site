<?php

namespace App\Services\Nin\Providers;

use App\Models\ServicePrice;
use App\Models\User;
use App\Services\Nin\Contracts\NinProvider;
use App\Services\Nin\VerificationResult;
use App\Services\Verification\VerificationDispatcher;
use App\Services\Verification\VerificationOutcome;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * The NIN verification provider: the routed chain configured in
 * Admin > Verification.
 *
 * This used to be one of six classes here, the others each hardcoding a single
 * third party (V1 Prembly, V2 ArewaSmart, V3..V5 placeholders). Those are gone:
 * picking between them was what the version selector on the verification pages
 * did, and that choice belongs to the routing chain, which also gives failover
 * the versioned providers never had.
 *
 * It stays behind the NinProvider contract so AbstractProviderController keeps
 * owning pricing, the wallet debit/refund and the Validation log.
 */
class RoutedProvider implements NinProvider
{
    /** Verification method (as the UI names it) => engine service key. */
    private const SERVICE_FOR_METHOD = [
        'nin' => 'nin.verify',
        'phone' => 'nin.phone',
        'demographic' => 'nin.demographic',
    ];

    public function __construct(private readonly VerificationDispatcher $dispatcher) {}

    public function key(): string
    {
        return 'auto';
    }

    public function label(): string
    {
        return 'Smart Routing';
    }

    /**
     * Verification methods are priced once, in Admin > Service Prices, and the
     * fee depends on the caller's role so an AGENT or API reseller can pay a
     * different rate.
     *
     * Null means unpriced or switched off — callers must refuse rather than
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

    /** Live as soon as one configured provider can serve one NIN method. */
    public function isActive(): bool
    {
        return $this->supportedMethods() !== [];
    }

    /**
     * Only the methods that actually have a usable provider behind them — a
     * method with an empty chain must not be offered, or the user pays for a
     * request that can only fail.
     */
    public function supportedMethods(): array
    {
        return array_values(array_keys(array_filter(
            self::SERVICE_FOR_METHOD,
            fn (string $service) => $this->dispatcher->chainFor($service)->isNotEmpty(),
        )));
    }

    public function verifyByNin(string $nin): VerificationResult
    {
        return $this->dispatch('nin', ['nin' => $nin]);
    }

    public function verifyByPhone(string $phone): VerificationResult
    {
        return $this->dispatch('phone', ['phone' => $phone]);
    }

    public function verifyByDemographic(array $demographic): VerificationResult
    {
        return $this->dispatch('demographic', [
            'first_name' => $demographic['first_name'],
            'last_name' => $demographic['last_name'],
            'middle_name' => $demographic['middle_name'] ?? null,
            'gender' => $demographic['gender'],
            // The engine's canonical date format is Y-m-d, which is what the UI
            // already sends; per-provider reformatting is the field map's job.
            'date_of_birth' => $demographic['date_of_birth'],
        ]);
    }

    /**
     * The engine speaks canonical names (`last_name`, `date_of_birth`); the NIN
     * verification screens and the slip components were written against the
     * provider-native NIMC spellings (`surname`, `dob`). Emitting both keeps the
     * existing templates rendering while new code can use the canonical names.
     *
     * Without this, a verification succeeds and is charged for but the result
     * panel renders blank fields.
     *
     * @param  array<string, mixed>  $data  canonical
     * @return array<string, mixed>
     */
    private function withLegacyAliases(array $data): array
    {
        $aliases = [
            'surname' => 'last_name',
            'firstname' => 'first_name',
            'middlename' => 'middle_name',
            'dob' => 'date_of_birth',
            'birthdate' => 'date_of_birth',
            'telephoneno' => 'phone',
            'residence_AdressLine' => 'residence_address',
            'address' => 'residence_address',
            'state' => 'residence_state',
            'lga' => 'residence_lga',
            'self_origin_state' => 'state_of_origin',
            'self_origin_lga' => 'lga_of_origin',
            'photo_path' => 'photo',
            'signature_path' => 'signature',
        ];

        foreach ($aliases as $legacy => $canonical) {
            if (! isset($data[$legacy]) && isset($data[$canonical])) {
                $data[$legacy] = $data[$canonical];
            }
        }

        // The screens show "othernames" as one field.
        if (! isset($data['othernames'])) {
            $othernames = trim(($data['first_name'] ?? '').' '.($data['middle_name'] ?? ''));

            if ($othernames !== '') {
                $data['othernames'] = $othernames;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function dispatch(string $method, array $input): VerificationResult
    {
        if (! in_array($method, $this->supportedMethods(), true)) {
            return VerificationResult::failure(
                'No verification provider is configured for this method. Please contact support.',
                'provider_unavailable',
                503,
            );
        }

        $service = self::SERVICE_FOR_METHOD[$method];

        try {
            $outcome = $this->dispatcher->verify($service, array_filter(
                $input,
                fn ($value) => $value !== null && $value !== '',
            ), ['user_id' => Auth::id()]);
        } catch (\Throwable $e) {
            // The dispatcher already turns provider-level failures into
            // outcomes; reaching here means something in our own code broke.
            Log::error("[NIN][{$service}] dispatch error: ".$e->getMessage());

            return VerificationResult::failure(
                'An unexpected error occurred while verifying. You were not charged.',
                'provider_error',
                502,
            );
        }

        return $this->toVerificationResult($outcome, $service);
    }

    /**
     * Translate the engine's outcome into the result shape the NIN controllers
     * already understand.
     */
    private function toVerificationResult(VerificationOutcome $outcome, string $service): VerificationResult
    {
        if ($outcome->isSuccess()) {
            return VerificationResult::success(
                $this->withLegacyAliases($outcome->data) + ['provider' => $outcome->providerName],
                $outcome->raw,
            );
        }

        // A timeout on a lookup means every provider in the chain was tried and
        // none answered — a 504 tells the caller to retry rather than assume the
        // record does not exist.
        $isTimeout = $outcome->isTimeout();

        return VerificationResult::failure(
            $outcome->message ?? 'Verification failed.',
            $isTimeout ? 'timeout' : 'verification_failed',
            $isTimeout ? 504 : 422,
            [
                'service' => $service,
                'attempts' => $outcome->attempts,
                'response' => $outcome->raw,
            ],
        );
    }
}
