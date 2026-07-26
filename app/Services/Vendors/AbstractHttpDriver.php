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
        return $this->send('post', $url, $headers, $payload, $errorResponseIsFail);
    }

    /**
     * GET and classify identically. Used by requery against vendors that expose
     * order status as a resource (GET {base}/{reference}) rather than a POST.
     *
     * @param  array<string, string>  $headers
     */
    protected function get(string $url, array $headers, bool $errorResponseIsFail = false): VendorResult
    {
        return $this->send('get', $url, $headers, [], $errorResponseIsFail);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $url, array $headers, array $payload, bool $errorResponseIsFail): VendorResult
    {
        try {
            $request = Http::timeout(30)
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
                ]);

            $response = $method === 'get'
                ? $request->get($url)
                : $request->post($url, $payload);
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

        // Accepted but not delivered yet. Ambiguous, so the purchase stays
        // `processing` and reconciliation settles it -- the reference is carried
        // through because requery needs the vendor's own handle for the order.
        if ($this->isPending($json)) {
            return VendorResult::timeout(
                $this->messageFrom($json) ?? 'Vendor accepted the order; delivery pending',
                $raw,
                $status,
                $this->referenceFrom($json),
            );
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
     * The DELIVERY state, which is not always the envelope status.
     *
     * Asynchronous vendors answer `status: success` to mean "order accepted"
     * and carry the real state in `transaction_status` or `data.status`. Reading
     * the envelope as delivery marks a purchase complete the moment it is
     * queued -- so if the vendor later fails and refunds, we have already told
     * the buyer it worked and kept their money.
     *
     * Only a RECOGNISED nested state wins. A vendor using `data.status` for
     * something unrelated must not be misread, so anything unfamiliar falls
     * back to the envelope.
     *
     * @param  array<string, mixed>  $json
     */
    protected function deliveryStatus(array $json): string
    {
        $known = [
            'success', 'successful', 'completed', 'delivered',
            'fail', 'failed', 'error', 'rejected',
            'pending', 'processing', 'queued', 'in_progress', 'initiated',
        ];

        foreach ([$json['transaction_status'] ?? null, $json['data']['status'] ?? null] as $candidate) {
            $candidate = strtolower(trim((string) $candidate));

            if ($candidate !== '' && in_array($candidate, $known, true)) {
                return $candidate;
            }
        }

        return strtolower((string) ($json['status'] ?? $json['Status'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function isSuccess(array $json): bool
    {
        return in_array(
            $this->deliveryStatus($json),
            ['success', 'successful', 'completed', 'delivered'],
            true,
        );
    }

    /**
     * Accepted but not yet delivered. Ambiguous by definition: the order is
     * live at the vendor, so it must never be failed over or refunded on the
     * spot -- it is left for reconciliation to settle.
     *
     * @param  array<string, mixed>  $json
     */
    protected function isPending(array $json): bool
    {
        return in_array(
            $this->deliveryStatus($json),
            ['pending', 'processing', 'queued', 'in_progress', 'initiated'],
            true,
        );
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
        $keys = ['reference', 'request-id', 'request_id', 'id', 'transaction_id', 'transactionId'];

        // `data` first: an async vendor returns its own reference nested there,
        // and that is the handle reconciliation needs later. The top level of
        // such a reply echoes OUR request id back, which is useless for a
        // requery -- it identifies the order on our side, not theirs.
        foreach ([$json['data'] ?? null, $json] as $scope) {
            if (! is_array($scope)) {
                continue;
            }

            foreach ($keys as $key) {
                if (! empty($scope[$key])) {
                    return (string) $scope[$key];
                }
            }
        }

        return null;
    }
}
