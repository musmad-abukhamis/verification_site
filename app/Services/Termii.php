<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Termii SMS client.
 *
 * Only plain SMS is used. Termii also exposes an OTP endpoint that generates
 * and stores the PIN on their side, but then verification depends on their
 * service being reachable, and attempt limits live somewhere we cannot audit.
 * We generate the code, hash it into the OTP table, and send the text ourselves.
 */
class Termii
{
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
}
