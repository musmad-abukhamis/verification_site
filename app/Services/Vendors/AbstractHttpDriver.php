<?php

namespace App\Services\Vendors;

use Illuminate\Support\Facades\Http;

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
                ->withHeaders($headers + ['Content-Type' => 'application/json'])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            return VendorResult::timeout('Vendor request failed: '.$e->getMessage());
        }

        $json = $response->json() ?? [];
        $status = $response->status();

        if (! $response->successful()) {
            $message = $this->messageFrom($json) ?? ('HTTP '.$status);
            $body = is_array($json) ? $json : [];

            return $errorResponseIsFail
                ? VendorResult::fail($message, $body, $status, failoverSafe: $status < 500)
                : VendorResult::timeout($message, $body, $status);
        }

        if ($this->isSuccess($json)) {
            return VendorResult::success($this->referenceFrom($json), $json, $this->messageFrom($json), $status);
        }

        return VendorResult::fail($this->messageFrom($json) ?? 'Vendor rejected the transaction', $json, $status);
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
