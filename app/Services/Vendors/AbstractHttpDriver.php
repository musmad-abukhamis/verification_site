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
     * A connection error, timeout, or non-2xx HTTP status → timeout (ambiguous,
     * must NOT fail over — could still be delivered).
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    protected function post(string $url, array $headers, array $payload): VendorResult
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders($headers + ['Content-Type' => 'application/json'])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            return VendorResult::timeout('Vendor request failed: '.$e->getMessage());
        }

        $json = $response->json() ?? [];

        if (! $response->successful()) {
            return VendorResult::timeout(
                $this->messageFrom($json) ?? ('HTTP '.$response->status()),
                is_array($json) ? $json : [],
            );
        }

        if ($this->isSuccess($json)) {
            return VendorResult::success($this->referenceFrom($json), $json, $this->messageFrom($json));
        }

        return VendorResult::fail($this->messageFrom($json) ?? 'Vendor rejected the transaction', $json);
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
