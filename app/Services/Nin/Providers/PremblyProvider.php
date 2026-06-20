<?php

namespace App\Services\Nin\Providers;

use App\Services\Nin\VerificationResult;

/**
 * Prembly (live).
 *
 * By-NIN  -> POST {base}/api/v1/nin/verify_1  { idType, idValue, slipType }
 * By-phone-> POST {base}/api/v1/nin/phone     { value, idType, idValue, ref }
 * By-demo -> POST {base}/api/v1/nin/demo      { firstName, lastName, gender, dateOfBirth(dd-mm-yyyy), ref }
 *
 * These payloads match the previously tested controllers exactly.
 */
class PremblyProvider extends AbstractNinProvider
{
    public function key(): string
    {
        return 'prembly';
    }

    public function verifyByNin(string $nin): VerificationResult
    {
        return $this->attempt('nin', function () use ($nin) {
            $response = $this->http()->post($this->baseUrl() . '/api/v1/nin/verify_1', [
                'idType'   => 'nin',
                'idValue'  => $nin,
                'slipType' => 'standard',
            ]);

            return $this->normalize($response, ['nin' => $nin]);
        });
    }

    public function verifyByPhone(string $phone): VerificationResult
    {
        return $this->attempt('phone', function () use ($phone) {
            $response = $this->http()->post($this->baseUrl() . '/api/v1/nin/phone', [
                'value'   => $phone,
                'idType'  => 'phone',
                'idValue' => $phone,
                'ref'     => null,
            ]);

            return $this->normalize($response);
        });
    }

    public function verifyByDemographic(array $demographic): VerificationResult
    {
        return $this->attempt('demographic', function () use ($demographic) {
            $response = $this->http()->post($this->baseUrl() . '/api/v1/nin/demo', [
                'firstName'   => $demographic['first_name'],
                'lastName'    => $demographic['last_name'],
                'gender'      => $demographic['gender'],
                // Provider expects dd-mm-yyyy; UI sends YYYY-MM-DD.
                'dateOfBirth' => $this->toProviderDob($demographic['date_of_birth']),
                'ref'         => null,
            ]);

            return $this->normalize($response);
        });
    }

    /**
     * Translate a raw HTTP response into a normalized result.
     */
    protected function normalize(\Illuminate\Http\Client\Response $response, array $extra = []): VerificationResult
    {
        $body = $response->json() ?? [];

        if ($response->successful() && !isset($body['error'])) {
            return VerificationResult::success(array_merge($extra, $body), $body);
        }

        return VerificationResult::failure(
            $this->extractMessage($body),
            'verification_failed',
            $response->status() >= 400 ? $response->status() : 422,
            $body ?: ['raw' => substr($response->body(), 0, 2000)],
        );
    }

    protected function toProviderDob(string $isoDate): string
    {
        // YYYY-MM-DD -> dd-mm-yyyy (defensive: return as-is if unexpected)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $isoDate, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return $isoDate;
    }
}
