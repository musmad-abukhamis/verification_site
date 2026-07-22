<?php

namespace App\Services\Verification;

use App\Models\VerificationEndpoint;
use App\Models\VerificationProvider;

/**
 * Turns canonical inputs into the exact request one provider expects.
 *
 * The engine always speaks in canonical names — `nin`, `phone`, `date_of_birth`,
 * `first_name`. An endpoint's `field_map` renames them to whatever that provider
 * calls them, and can translate the value on the way:
 *
 *   "nin": "number"                                     → {"number": "..."}
 *   "date_of_birth": {"field":"dateOfBirth","format":"d-m-Y"}
 *   "gender": {"field":"gender","values":{"male":"M","female":"F"}}
 *   "nin": {"field":"data.nin"}                         → {"data":{"nin":"..."}}
 *
 * Anything not named in the map passes through under its canonical name, so a
 * provider that already agrees with us needs no map at all.
 */
class RequestBuilder
{
    /**
     * The request to send.
     *
     * @param  array<string, mixed>  $input  canonical inputs
     * @return array{
     *     url: string, method: string, body_type: string,
     *     headers: array<string, string>, body: array<string, mixed>, query: array<string, mixed>
     * }
     */
    public function build(
        VerificationProvider $provider,
        VerificationEndpoint $endpoint,
        array $input,
        ?string $reference = null,
    ): array {
        $credentials = (array) $provider->credentials;
        $authConfig = (array) $provider->auth_config;

        $body = $this->mapFields($endpoint->field_map ?? [], $input);

        foreach ($this->resolveStatics($endpoint->static_fields ?? [], $input, $reference) as $key => $value) {
            $this->setPath($body, $key, $value);
        }

        // The auth style may want to ride along in the body or the query string
        // instead of a header (TechHub posts its api_key in the body).
        $body += AuthStyle::bodyFields($provider->auth_type, $credentials, $authConfig);
        $query = AuthStyle::queryFields($provider->auth_type, $credentials, $authConfig);

        $headers = AuthStyle::headers($provider->auth_type, $credentials, $authConfig)
            + (array) ($provider->extra_headers ?? []);

        // A GET endpoint carries its mapped fields as query parameters — there
        // is no body to put them in.
        if ($endpoint->isGet()) {
            $query += $body;
            $body = [];
        }

        return [
            'url' => $this->resolveUrl($endpoint->url($provider->base_url), $input, $reference),
            'method' => strtoupper($endpoint->http_method),
            'body_type' => $endpoint->body_type ?: 'json',
            'headers' => $headers,
            'body' => $body,
            'query' => $query,
        ];
    }

    /**
     * The same request with every credential removed, safe to persist as the
     * attempt audit payload.
     *
     * @param  array<string, mixed>  $request  as returned by build()
     * @return array<string, mixed>
     */
    public function sanitize(VerificationProvider $provider, array $request): array
    {
        $credentials = array_filter(array_map(
            fn ($v) => is_scalar($v) ? (string) $v : null,
            (array) $provider->credentials,
        ));

        $scrub = function ($value) use (&$scrub, $credentials) {
            if (is_array($value)) {
                return array_map($scrub, $value);
            }

            if (! is_string($value)) {
                return $value;
            }

            // Any field whose value *is* a credential gets masked, wherever it
            // sits — body, query or the URL itself.
            foreach ($credentials as $secret) {
                if ($secret !== '' && str_contains($value, $secret)) {
                    $value = str_replace($secret, '***', $value);
                }
            }

            return $value;
        };

        return [
            'url' => $scrub($request['url']),
            'method' => $request['method'],
            'body' => $scrub($request['body']),
            'query' => $scrub($request['query']),
            // Header *values* are dropped entirely; only the names are useful
            // for debugging and the values are exactly what must not be stored.
            'headers' => array_keys($request['headers']),
        ];
    }

    /**
     * Rename and translate canonical inputs into provider field names.
     *
     * @param  array<string, mixed>  $map
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function mapFields(array $map, array $input): array
    {
        $body = [];

        foreach ($input as $canonical => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $spec = $map[$canonical] ?? null;

            // No mapping for this input: a map that is present but silent about
            // a field still forwards it, because most providers only rename one
            // or two fields and listing the rest would be noise.
            if ($spec === null) {
                $this->setPath($body, $canonical, $value);

                continue;
            }

            // `false` explicitly drops a field the provider rejects.
            if ($spec === false) {
                continue;
            }

            if (is_string($spec)) {
                $spec = ['field' => $spec];
            }

            $field = $spec['field'] ?? $canonical;

            $this->setPath($body, $field, $this->transform($value, $spec));
        }

        return $body;
    }

    /**
     * Apply a field spec's value translation: date reformat, value lookup,
     * case change, digit stripping.
     *
     * @param  array<string, mixed>  $spec
     */
    protected function transform(mixed $value, array $spec): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        // Date reformat. The canonical wire format is Y-m-d; providers ask for
        // d-m-Y, d/m/Y, Ymd... An unparseable date passes through untouched
        // rather than becoming a bogus "1970-01-01".
        if (! empty($spec['format'])) {
            $parsed = date_create($value);
            if ($parsed !== false) {
                $value = $parsed->format($spec['format']);
            }
        }

        // Value lookup, e.g. gender male → M. Matched case-insensitively so the
        // table does not need every spelling.
        if (! empty($spec['values']) && is_array($spec['values'])) {
            $lookup = array_change_key_case($spec['values'], CASE_LOWER);
            $value = $lookup[strtolower($value)] ?? $value;
        }

        if (! empty($spec['digits_only'])) {
            $value = preg_replace('/\D+/', '', $value) ?? $value;
        }

        return match ($spec['transform'] ?? null) {
            'upper' => strtoupper($value),
            'lower' => strtolower($value),
            'title' => ucwords(strtolower($value)),
            default => $value,
        };
    }

    /**
     * Resolve `{placeholder}` tokens in static field values.
     *
     * @param  array<string, mixed>  $statics
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function resolveStatics(array $statics, array $input, ?string $reference): array
    {
        return array_map(
            fn ($value) => is_string($value) ? $this->interpolate($value, $input, $reference) : $value,
            $statics,
        );
    }

    /**
     * Endpoint paths may embed inputs, e.g. `/nin/{nin}/verify`.
     *
     * @param  array<string, mixed>  $input
     */
    protected function resolveUrl(string $url, array $input, ?string $reference): string
    {
        return $this->interpolate($url, $input, $reference);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function interpolate(string $subject, array $input, ?string $reference): string
    {
        if (! str_contains($subject, '{')) {
            return $subject;
        }

        $tokens = ['{reference}' => (string) $reference, '{ref}' => (string) $reference];

        foreach ($input as $key => $value) {
            if (is_scalar($value)) {
                $tokens['{'.$key.'}'] = (string) $value;
            }
        }

        return strtr($subject, $tokens);
    }

    /**
     * Write a value at a dotted path so a field map can target a nested body.
     *
     * @param  array<string, mixed>  $target
     */
    protected function setPath(array &$target, string $path, mixed $value): void
    {
        if (! str_contains($path, '.')) {
            $target[$path] = $value;

            return;
        }

        $cursor = &$target;

        foreach (explode('.', $path) as $segment) {
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }

        $cursor = $value;
    }
}
