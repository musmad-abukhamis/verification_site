<?php

namespace App\Services\Vendors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Shared HTTP plumbing + success/ambiguity classification for token-style
 * drivers.
 */
abstract class AbstractHttpDriver implements VendorDriverInterface
{
    /**
     * POST a JSON body and classify the outcome.
     *
     * A 2xx response whose status field is success/successful → success.
     * A 2xx response with any other status → explicit fail (safe to fail over).
     * A non-2xx HTTP status → explicit fail: the vendor answered, so the buyer
     * gets a verdict now instead of waiting on reconciliation. 4xx is safe to
     * fail over (the vendor rejected the request outright); 5xx is not, because
     * it may have been delivered before the vendor broke.
     * A connection error or read timeout → timeout (genuinely ambiguous: no
     * response at all, so it must NOT fail over — could still be delivered).
     *
     * $errorResponseIsFail must be false for requery probes. A requery has no
     * agreed contract at most vendors -- a 404 there means "no such endpoint",
     * not "the purchase failed" -- so treating it as an explicit fail would make
     * reconciliation refund a delivered purchase instead of holding it as
     * unconfirmed for the admin exceptions report.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    protected function post(string $url, array $headers, array $payload, bool $errorResponseIsFail = true): VendorResult
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders($headers + [
                    'Content-Type' => 'application/json',
                    // Accept is not optional. A Laravel-based vendor (this app
                    // resells through a sibling deployment) content-negotiates
                    // on it: without Accept a validation failure is a 302 back
                    // to the SPA, which the HTTP client follows and reports as
                    // a 200 full of HTML. The vendor's real complaint is lost,
                    // the body parses to [], and the call reads as a bare
                    // rejection with no message.
                    'Accept' => 'application/json',
                ])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            return VendorResult::timeout('Vendor request failed: '.$e->getMessage());
        }

        $decoded = $response->json();
        $json = is_array($decoded) ? $decoded : [];
        $status = $response->status();

        // Keep something of a non-JSON reply. Discarding it is what made a
        // misrouted call unreadable in the audit: an empty response and a null
        // message look identical whether the vendor said nothing or answered
        // with an HTML error page.
        $isJson = is_array($decoded);
        $raw = $isJson ? $json : ['_non_json_body' => Str::limit($response->body(), 1000)];
        $nonJsonNote = 'Vendor returned a non-JSON response (HTTP '.$status.')';

        if (! $response->successful()) {
            $message = $this->messageFrom($json) ?? ($isJson ? 'HTTP '.$status : $nonJsonNote);

            return $errorResponseIsFail
                ? VendorResult::fail($message, $raw, $status, failoverSafe: $status < 500)
                : VendorResult::timeout($message, $raw, $status);
        }

        if ($this->isSuccess($json)) {
            return VendorResult::success($this->referenceFrom($json), $raw, $this->messageFrom($json), $status);
        }

        // A 2xx that is not JSON is not a rejection -- it is a misrouted call
        // (a followed redirect, a captive portal, a WAF page). Treating it as an
        // explicit fail would let failover fire on a request whose fate is
        // unknown, so it stays ambiguous.
        if (! $isJson) {
            return VendorResult::timeout($nonJsonNote, $raw, $status);
        }

        return VendorResult::fail($this->messageFrom($json) ?? 'Vendor rejected the transaction', $raw, $status);
    }

    /**
     * The Authorization header for a token, honouring the vendor's scheme.
     *
     * Scheme and body shape are independent: vendors in this market mix
     * `Authorization: Token {key}` and `Authorization: Bearer {key}` across
     * identical payloads. Keeping the scheme as vendor config rather than baking
     * it into the driver avoids a token_style_a_bearer / token_style_b_bearer
     * combinatorial explosion.
     *
     * Defaults to Token so vendors configured before the scheme existed keep
     * working untouched.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    protected function authHeader(array $credentials, ?string $token = null): array
    {
        $scheme = trim((string) ($credentials['scheme'] ?? '')) ?: 'Token';

        return ['Authorization' => $scheme.' '.($token ?? $credentials['key'] ?? '')];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function isSuccess(array $json): bool
    {
        $status = strtolower((string) ($json['status'] ?? $json['Status'] ?? ''));

        return in_array($status, ['success', 'successful'], true);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function messageFrom(array $json): ?string
    {
        foreach (['message', 'Message', 'msg', 'detail', 'error'] as $key) {
            if (! empty($json[$key]) && is_string($json[$key])) {
                return $json[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function referenceFrom(array $json): ?string
    {
        foreach (['reference', 'request-id', 'request_id', 'id', 'transaction_id', 'transactionId'] as $key) {
            if (! empty($json[$key])) {
                return (string) $json[$key];
            }
        }

        return null;
    }
}
