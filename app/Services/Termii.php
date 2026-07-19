<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Termii client, in two modes (services.termii.mode).
 *
 * "plain" is what we want: we generate the code, hash it into the OTP table,
 * and Termii only carries the text. Attempt limits and expiry stay in our
 * database, and a reset does not depend on Termii being reachable twice.
 *
 * "otp" is what this account can actually do. Plain SMS returns 400 "Country
 * Inactive"; the OTP product is active (nimcweb used it). Termii generates the
 * code, we store the pin_id it returns, and verification is a second call to
 * their API -- so a reset in flight fails if that endpoint is down.
 */
class Termii
{
    public function mode(): string
    {
        return config('services.termii.mode', 'otp');
    }

    public function usesRemoteOtp(): bool
    {
        return $this->mode() === 'otp';
    }

    public function configured(): bool
    {
        return filled(config('services.termii.key'));
    }

    /**
     * Nigerian numbers reach Termii as +234XXXXXXXXXX. Accounts store them as
     * 0XXXXXXXXXX, 234..., or +234... interchangeably, so normalise here rather
     * than trusting the column.
     */
    public function formatPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 10) {
            return null;
        }

        return '+234'.substr($digits, -10);
    }

    /**
     * Returns true only when Termii accepted the message. Callers must not
     * treat a false as "no such user" -- it means delivery failed.
     */
    public function send(string $phone, string $message): bool
    {
        $to = $this->formatPhone($phone);

        if (! $to || ! $this->configured()) {
            Log::warning('Termii send skipped', [
                'reason' => $to ? 'no api key configured' : 'unusable phone number',
            ]);

            return false;
        }

        try {
            $response = Http::timeout(15)
                ->post(rtrim(config('services.termii.base_url'), '/').'/api/sms/send', [
                    'api_key' => config('services.termii.key'),
                    'to' => $to,
                    'from' => config('services.termii.sender'),
                    'sms' => $message,
                    'type' => 'plain',
                    'channel' => config('services.termii.channel'),
                ]);
        } catch (\Throwable $e) {
            Log::error('Termii request failed', ['error' => $e->getMessage()]);

            return false;
        }

        // Termii can answer 200 with a body describing a failure, so the status
        // code alone does not mean the message went out -- an accepted message
        // always carries a message_id.
        if (! $response->successful() || blank($response->json('message_id'))) {
            Log::error('Termii rejected message', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Ask Termii to generate a code, text it, and remember it.
     *
     * Returns the pin_id needed to verify later, or null if nothing was sent.
     * The code itself is never revealed to us -- verification has to go back
     * through verifyOtp().
     */
    public function sendOtp(string $phone, string $messageTemplate, int $length, int $ttlMinutes, int $attempts): ?string
    {
        $to = $this->formatPhone($phone);

        if (! $to || ! $this->configured()) {
            Log::warning('Termii OTP send skipped', [
                'reason' => $to ? 'no api key configured' : 'unusable phone number',
            ]);

            return null;
        }

        // Termii substitutes its generated code for this placeholder in the
        // message text; it must appear in the template verbatim.
        $placeholder = '<1234>';

        try {
            $response = Http::timeout(15)
                ->post(rtrim(config('services.termii.base_url'), '/').'/api/sms/otp/send', [
                    'api_key' => config('services.termii.key'),
                    'message_type' => 'NUMERIC',
                    'to' => $to,
                    'from' => config('services.termii.sender'),
                    'channel' => config('services.termii.channel'),
                    'pin_attempts' => $attempts,
                    'pin_time_to_live' => $ttlMinutes,
                    'pin_length' => $length,
                    'pin_placeholder' => $placeholder,
                    'message_text' => str_replace(':code', $placeholder, $messageTemplate),
                    'pin_type' => 'NUMERIC',
                ]);
        } catch (\Throwable $e) {
            Log::error('Termii OTP request failed', ['error' => $e->getMessage()]);

            return null;
        }

        $pinId = $response->json('pinId') ?? $response->json('pin_id');

        if (! $response->successful() || blank($pinId)) {
            Log::error('Termii rejected OTP request', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $pinId;
    }

    /**
     * Check a code against Termii. False covers both "wrong code" and "could
     * not ask" -- callers must not read it as proof the code was wrong.
     */
    public function verifyOtp(string $pinId, string $pin): bool
    {
        if (! $this->configured()) {
            return false;
        }

        try {
            $response = Http::timeout(15)
                ->post(rtrim(config('services.termii.base_url'), '/').'/api/sms/otp/verify', [
                    'api_key' => config('services.termii.key'),
                    'pin_id' => $pinId,
                    'pin' => $pin,
                ]);
        } catch (\Throwable $e) {
            Log::error('Termii OTP verify failed', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            // A wrong or expired pin is a 400 here, so this is not necessarily
            // a fault -- log at info to avoid crying wolf.
            Log::info('Termii OTP verify rejected', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        // Termii has returned this as both boolean true and the string "true".
        return filter_var($response->json('verified'), FILTER_VALIDATE_BOOLEAN);
    }
}
