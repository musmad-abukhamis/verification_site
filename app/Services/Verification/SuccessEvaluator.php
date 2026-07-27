<?php

namespace App\Services\Verification;

/**
 * Decides whether a provider's reply means "found", "not found" or "no idea".
 *
 * The distinction matters because it drives failover and refunds: an explicit
 * "not found" moves to the next provider immediately, while an ambiguous reply
 * may have to stop the chain (see ServiceCatalog::isIdempotent).
 *
 * Providers signal success in incompatible ways — `"status": true`,
 * `"status": "success"`, `"success": true`, `"response_code": "00"`, ArewaSmart's
 * `111111`, or merely the absence of an `error` key. An endpoint may pin the
 * rule down explicitly:
 *
 *   {"path": "status", "in": ["success"], "error_path": "error"}
 *
 * With no rule, the heuristic below covers every provider observed in this
 * market. Note that a *charged* "not found" (ArewaSmart 222222, "BVN does not
 * exist") is still a fail: the user did not get a record.
 */
class SuccessEvaluator
{
    /** Values that mean success wherever they appear in a status-ish field. */
    private const TRUTHY = [
        'true', '1', 'success', 'successful', 'ok', 'completed', 'approved',
        'found', 'verified', '00', '000', '111111',
    ];

    /** Fields providers put their success signal in, most specific first. */
    private const STATUS_KEYS = ['status', 'success', 'response_code', 'responseCode', 'code'];

    /** Fields that, when non-empty, mean the call failed. */
    private const ERROR_KEYS = ['error', 'errors', 'error_message', 'errorMessage'];

    /**
     * @param  array<string, mixed>  $body  decoded response
     * @param  array<string, mixed>|null  $rule  the endpoint's success_rule
     */
    public function isSuccess(array $body, ?array $rule, int $httpStatus): bool
    {
        if ($httpStatus < 200 || $httpStatus >= 300) {
            return false;
        }

        if (! empty($rule)) {
            return $this->matchesRule($body, $rule);
        }

        return $this->heuristic($body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $rule
     */
    protected function matchesRule(array $body, array $rule): bool
    {
        // An error field named by the rule vetoes success outright.
        if (! empty($rule['error_path'])) {
            $error = $this->valueAt($body, (string) $rule['error_path']);
            if ($error !== null && $error !== '' && $error !== false && $error !== []) {
                return false;
            }
        }

        // The rule may require the payload to actually carry a record — some
        // providers answer 200 {"status":"success","data":null} for a miss.
        if (! empty($rule['data_path'])) {
            $data = $this->valueAt($body, (string) $rule['data_path'], raw: true);
            if (! is_array($data) || $data === []) {
                return false;
            }
        }

        if (empty($rule['path'])) {
            // A rule with only error/data checks and no status field: passing
            // those is enough.
            return ! empty($rule['error_path']) || ! empty($rule['data_path']);
        }

        $value = $this->valueAt($body, (string) $rule['path']);

        if ($value === null) {
            return false;
        }

        $accepted = $rule['in'] ?? $rule['equals'] ?? null;

        if (is_array($accepted) && $accepted !== []) {
            return in_array($this->stringify($value), array_map(
                fn ($v) => strtolower(trim((string) $v)),
                $accepted,
            ), true);
        }

        return in_array($this->stringify($value), self::TRUTHY, true);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function heuristic(array $body): bool
    {
        foreach (self::ERROR_KEYS as $key) {
            $value = $body[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== false && $value !== []) {
                return false;
            }
        }

        $sawStatusField = false;

        foreach (self::STATUS_KEYS as $key) {
            if (! array_key_exists($key, $body)) {
                continue;
            }

            $sawStatusField = true;

            if (in_array($this->stringify($body[$key]), self::TRUTHY, true)) {
                return true;
            }
        }

        // A status field that said something else is a definitive "no".
        if ($sawStatusField) {
            return false;
        }

        // No status field at all: a 2xx carrying a populated `data` object is
        // the record itself. Prembly's vnin-basic answers this way.
        $data = $body['data'] ?? $body['user_data'] ?? null;

        return (is_array($data) && $data !== []) || $this->looksLikeRecord($body);
    }

    /**
     * A bare 2xx body with identity-looking fields and no envelope.
     *
     * @param  array<string, mixed>  $body
     */
    protected function looksLikeRecord(array $body): bool
    {
        foreach (['nin', 'bvn', 'surname', 'first_name', 'firstName', 'firstname', 'lastName', 'last_name'] as $key) {
            if (! empty($body[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The provider's own explanation, for the audit trail and the user.
     *
     * @param  array<string, mixed>  $body
     */
    public function message(array $body, ?string $fallback = null): ?string
    {
        foreach (['message', 'detail', 'error_message', 'errorMessage', 'msg', 'description', 'error', 'reason'] as $key) {
            $value = $body[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $fallback;
    }

    /**
     * The provider's reference for this call, when it gives one.
     *
     * @param  array<string, mixed>  $body
     */
    public function reference(array $body): ?string
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        foreach (['reference', 'transaction_ref', 'trx_ref', 'verification_reference', 'request_id', 'transactionId', 'id'] as $key) {
            foreach ([$body, $data] as $source) {
                if (! empty($source[$key]) && is_scalar($source[$key])) {
                    return (string) $source[$key];
                }
            }
        }

        return null;
    }

    /**
     * Read a dotted path; `raw` keeps arrays instead of flattening to a scalar.
     *
     * @param  array<string, mixed>  $body
     */
    protected function valueAt(array $body, string $path, bool $raw = false): mixed
    {
        $cursor = $body;

        foreach (explode('.', $path) as $segment) {
            if (is_array($cursor) && array_key_exists($segment, $cursor)) {
                $cursor = $cursor[$segment];

                continue;
            }

            return null;
        }

        if ($raw) {
            return $cursor;
        }

        return is_scalar($cursor) ? $cursor : null;
    }

    protected function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return strtolower(trim((string) $value));
    }
}
