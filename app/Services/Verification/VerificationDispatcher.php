<?php

namespace App\Services\Verification;

use App\Models\VerificationAttempt;
use App\Models\VerificationEndpoint;
use App\Models\VerificationProvider;
use App\Models\VerificationRoute;
use App\Models\VerificationSetting;
use Illuminate\Support\Collection;

/**
 * Runs a verification against the provider chain configured for its service,
 * with admin-gated failover, and records one attempt row per hop.
 *
 * Failover rules — the same shape as ProcessDataPurchase, with one deliberate
 * difference:
 *  - explicit provider fail → try the next provider (when failover is on);
 *  - ambiguous / timeout    → continue ONLY for idempotent services. A NIN or
 *    BVN lookup is a read, so asking someone else is safe. BVN retrieval and
 *    IPE clearance submit work upstream: a timeout there may mean the request
 *    landed, and re-sending it would duplicate a real submission, so the chain
 *    stops and the caller reconciles.
 *
 * The dispatcher never touches the wallet. Callers debit before calling and
 * refund on a non-success outcome, exactly as they do today.
 */
class VerificationDispatcher
{
    public function __construct(private readonly ProviderCaller $caller) {}

    /**
     * Verify against the chain for $service.
     *
     * @param  array<string, mixed>  $input  canonical inputs (see ServiceCatalog)
     * @param  array{user_id?: string|null, reference?: string|null, log?: bool}  $context
     */
    public function verify(string $service, array $input, array $context = []): VerificationOutcome
    {
        $userId = $context['user_id'] ?? null;
        $reference = $context['reference'] ?? null;
        $shouldLog = $context['log'] ?? true;

        $candidates = $this->chainFor($service);

        if ($candidates->isEmpty()) {
            return VerificationOutcome::fail(
                'No verification provider is configured for this service. Please contact support.',
                ['error' => 'no_provider_configured'],
            );
        }

        $failoverEnabled = VerificationSetting::bool('failover_enabled', true);
        $maxAttempts = VerificationSetting::int('failover_max_attempts', 0);
        if ($maxAttempts <= 0) {
            $maxAttempts = $candidates->count();
        }

        $idempotent = ServiceCatalog::isIdempotent($service);

        $trace = [];
        $last = null;
        $attempts = 0;

        foreach ($candidates as $candidate) {
            if ($attempts >= $maxAttempts) {
                break;
            }

            /** @var VerificationProvider $provider */
            $provider = $candidate['provider'];
            /** @var VerificationEndpoint $endpoint */
            $endpoint = $candidate['endpoint'];

            $attempts++;

            $call = $this->caller->call($provider, $endpoint, $input, $reference);
            /** @var VerificationOutcome $outcome */
            $outcome = $call['outcome'];

            if ($shouldLog) {
                $this->logAttempt($service, $provider, $outcome, $call, $userId, $reference);
            }

            $trace[] = [
                'provider' => $provider->name,
                'provider_id' => $provider->getKey(),
                'outcome' => $outcome->outcome,
                'http_status' => $outcome->httpStatus,
                'message' => $outcome->message,
                'duration_ms' => $call['duration_ms'],
            ];

            if ($outcome->isSuccess()) {
                return $outcome->withAttempts($trace);
            }

            $last = $outcome;

            // Ambiguous reply on a submission service: stop, so we never
            // duplicate an upstream ticket that may already exist.
            if ($outcome->isTimeout() && ! $idempotent) {
                break;
            }

            if (! $failoverEnabled) {
                break;
            }
        }

        return ($last ?? VerificationOutcome::fail('Verification failed.'))->withAttempts($trace);
    }

    /**
     * The ordered provider chain for a service.
     *
     * `verification_routes` is the authority. When a service has no routes
     * configured, every usable provider that implements it is tried in
     * `priority` order — so adding a provider makes it work immediately and
     * the routing screen becomes a refinement rather than a prerequisite.
     *
     * @return Collection<int, array{provider: VerificationProvider, endpoint: VerificationEndpoint}>
     */
    public function chainFor(string $service): Collection
    {
        $routed = VerificationRoute::forService($service)
            ->with(['provider.endpoints'])
            ->get()
            ->map(fn (VerificationRoute $route) => $route->provider)
            ->filter();

        if ($routed->isEmpty()) {
            $routed = VerificationProvider::active()
                ->with('endpoints')
                ->orderBy('priority')
                ->orderBy('name')
                ->get();
        }

        return $routed
            ->map(function (VerificationProvider $provider) use ($service) {
                $endpoint = $provider->endpointFor($service);

                // Skip a provider that is switched off, missing credentials, or
                // has no endpoint row for this service.
                if (! $endpoint || ! $provider->isUsable()) {
                    return null;
                }

                return ['provider' => $provider, 'endpoint' => $endpoint];
            })
            ->filter()
            ->values();
    }

    /**
     * Which providers would serve a service, for the admin routing screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function describeChain(string $service): array
    {
        return $this->chainFor($service)
            ->map(fn (array $c) => [
                'id' => $c['provider']->getKey(),
                'name' => $c['provider']->name,
                'path' => $c['endpoint']->path,
            ])
            ->all();
    }

    /**
     * @param  array{request: array<string, mixed>, duration_ms: int}  $call
     */
    private function logAttempt(
        string $service,
        VerificationProvider $provider,
        VerificationOutcome $outcome,
        array $call,
        ?string $userId,
        ?string $reference,
    ): void {
        VerificationAttempt::create([
            'service' => $service,
            'provider_id' => $provider->getKey(),
            'provider_name' => $provider->name,
            'user_id' => $userId,
            'reference' => $reference,
            'request_payload' => $call['request'], // already credential-free
            'response' => $this->truncateResponse($outcome->raw),
            'outcome' => $outcome->outcome,
            'http_status' => $outcome->httpStatus,
            'duration_ms' => $call['duration_ms'],
            'message' => $outcome->message,
        ]);
    }

    /**
     * Identity responses carry base64 photos and signatures that are hundreds
     * of KB each. Storing them on every hop would bloat the audit table for no
     * diagnostic value, so they are replaced by a size marker.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function truncateResponse(array $response): array
    {
        $prune = function ($value) use (&$prune) {
            if (is_array($value)) {
                return array_map($prune, $value);
            }

            if (is_string($value) && strlen($value) > 512) {
                return '['.strlen($value).' bytes omitted]';
            }

            return $value;
        };

        return array_map($prune, $response);
    }
}
