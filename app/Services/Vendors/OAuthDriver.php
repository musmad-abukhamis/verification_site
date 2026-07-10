<?php

namespace App\Services\Vendors;

use App\Models\DataTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * OAuth client-credentials vendor. Fetches an access token from `token_url`
 * (cached in the DB cache until shortly before expiry), then sends a
 * TokenStyle-A shaped body with `Authorization: Token {access_token}`.
 *
 * Credentials: {client_id, client_secret, token_url}.
 */
class OAuthDriver extends AbstractHttpDriver
{
    public function purchase(
        DataTransaction $txn,
        string $externalPlanId,
        string $externalNetworkCode,
        string $baseUrl,
        array $credentials,
    ): VendorResult {
        $token = $this->accessToken($credentials);

        if ($token === null) {
            return VendorResult::timeout('Unable to obtain vendor access token');
        }

        return $this->post(
            $baseUrl,
            ['Authorization' => 'Token '.$token],
            $this->describePayload($txn, $externalPlanId, $externalNetworkCode),
        );
    }

    public function requery(DataTransaction $txn, string $baseUrl, array $credentials): VendorResult
    {
        $token = $this->accessToken($credentials);

        if ($token === null) {
            return VendorResult::timeout('Unable to obtain vendor access token');
        }

        return $this->post(
            rtrim($baseUrl, '/').'/status',
            ['Authorization' => 'Token '.$token],
            ['request-id' => $txn->getKey()],
        );
    }

    public function describePayload(DataTransaction $txn, string $externalPlanId, string $externalNetworkCode): array
    {
        return [
            'network' => $externalNetworkCode,
            'phone' => $txn->phone,
            'bypass' => true,
            'data_plan' => $externalPlanId,
            'request-id' => $txn->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function accessToken(array $credentials): ?string
    {
        $tokenUrl = $credentials['token_url'] ?? null;
        $clientId = $credentials['client_id'] ?? null;
        $clientSecret = $credentials['client_secret'] ?? null;

        if (! $tokenUrl || ! $clientId || ! $clientSecret) {
            return null;
        }

        $cacheKey = 'vendor_oauth:'.md5($tokenUrl.'|'.$clientId);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::timeout(20)->asForm()->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        // Cache until 60s before expiry (default 5 min when unspecified).
        $expiresIn = (int) ($response->json('expires_in') ?? 300);
        Cache::put($cacheKey, $token, max(30, $expiresIn - 60));

        return $token;
    }
}
