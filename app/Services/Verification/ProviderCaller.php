<?php

namespace App\Services\Verification;

use App\Models\VerificationEndpoint;
use App\Models\VerificationProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls exactly one provider's endpoint and classifies the reply.
 *
 * Pure request/response translation: it never touches the wallet, never picks
 * the next provider and never persists anything. VerificationDispatcher owns
 * failover and the attempt log; controllers own the money.
 */
class ProviderCaller
{
    public function __construct(
        private readonly RequestBuilder $builder,
        private readonly SuccessEvaluator $evaluator,
        private readonly ResponseNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $input  canonical inputs
     * @return array{outcome: VerificationOutcome, request: array<string, mixed>, duration_ms: int}
     */
    public function call(
        VerificationProvider $provider,
        VerificationEndpoint $endpoint,
        array $input,
        ?string $reference = null,
    ): array {
        $request = $this->builder->build($provider, $endpoint, $input, $reference);
        $safeRequest = $this->builder->sanitize($provider, $request);

        $startedAt = microtime(true);

        try {
            $response = $this->send($provider, $endpoint, $request);
        } catch (ConnectionException $e) {
            // Never log $request — it holds the provider's credentials.
            Log::warning("[verify][{$provider->slug}][{$endpoint->service}] connection: ".$e->getMessage());

            return [
                'outcome' => VerificationOutcome::timeout(
                    'The verification provider did not respond in time.',
                    ['error' => $e->getMessage()],
                    $provider->getKey(),
                    $provider->name,
                ),
                'request' => $safeRequest,
                'duration_ms' => $this->elapsed($startedAt),
            ];
        } catch (\Throwable $e) {
            Log::error("[verify][{$provider->slug}][{$endpoint->service}] error: ".$e->getMessage());

            return [
                'outcome' => VerificationOutcome::timeout(
                    'An unexpected error occurred while contacting the provider.',
                    ['error' => $e->getMessage()],
                    $provider->getKey(),
                    $provider->name,
                ),
                'request' => $safeRequest,
                'duration_ms' => $this->elapsed($startedAt),
            ];
        }

        $duration = $this->elapsed($startedAt);
        $status = $response->status();
        $body = $response->json();

        // A non-JSON reply is almost always an HTML error page or a WAF block —
        // ambiguous, not a definitive "not found".
        if (! is_array($body)) {
            return [
                'outcome' => VerificationOutcome::timeout(
                    "The provider returned an unreadable response (HTTP {$status}).",
                    ['http_status' => $status, 'raw' => substr($response->body(), 0, 2000)],
                    $provider->getKey(),
                    $provider->name,
                    $status,
                ),
                'request' => $safeRequest,
                'duration_ms' => $duration,
            ];
        }

        if ($this->evaluator->isSuccess($body, $endpoint->success_rule, $status)) {
            $data = $this->normalizer->normalize(
                $body,
                (array) ($endpoint->response_map ?? []),
                $this->seedFrom($input),
            );

            return [
                'outcome' => VerificationOutcome::success(
                    $data,
                    $body,
                    $this->evaluator->message($body, 'Verification successful'),
                    $this->evaluator->reference($body),
                    $provider->getKey(),
                    $provider->name,
                    $status,
                ),
                'request' => $safeRequest,
                'duration_ms' => $duration,
            ];
        }

        $message = $this->evaluator->message($body);

        // 5xx and 429 are the provider's problem, not the record's — treat them
        // as ambiguous so a submission service does not resubmit blindly.
        if ($status >= 500 || $status === 429) {
            return [
                'outcome' => VerificationOutcome::timeout(
                    $message ?? "The provider is unavailable (HTTP {$status}).",
                    $body,
                    $provider->getKey(),
                    $provider->name,
                    $status,
                ),
                'request' => $safeRequest,
                'duration_ms' => $duration,
            ];
        }

        return [
            'outcome' => VerificationOutcome::fail(
                $message ?? 'The provider could not verify this record.',
                $body,
                $provider->getKey(),
                $provider->name,
                $status,
            ),
            'request' => $safeRequest,
            'duration_ms' => $duration,
        ];
    }

    /**
     * @param  array<string, mixed>  $request  as built by RequestBuilder
     */
    protected function send(
        VerificationProvider $provider,
        VerificationEndpoint $endpoint,
        array $request,
    ): \Illuminate\Http\Client\Response {
        $client = Http::timeout(max(5, (int) $provider->timeout_seconds))
            ->acceptJson()
            ->withHeaders($request['headers']);

        if ($request['body_type'] === 'form') {
            $client = $client->asForm();
        }

        $url = $request['url'];

        if ($request['query'] !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($request['query']);
        }

        return match ($request['method']) {
            'GET' => $client->get($url),
            'PUT' => $client->put($url, $request['body']),
            'PATCH' => $client->patch($url, $request['body']),
            default => $client->post($url, $request['body']),
        };
    }

    /**
     * The identifiers we already know, so a provider that omits them from its
     * reply still produces a complete record.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function seedFrom(array $input): array
    {
        return array_filter(
            array_intersect_key($input, array_flip(['nin', 'bvn', 'phone', 'tracking_id'])),
            fn ($v) => $v !== null && $v !== '',
        );
    }

    protected function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
