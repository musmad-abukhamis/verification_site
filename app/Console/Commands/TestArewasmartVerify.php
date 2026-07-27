<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Probe the external ArewaSmart verification API to confirm credentials and
 * capture the response shape before/while wiring it into the verify flows.
 *
 * Usage:
 *   php artisan arewasmart:test 22211960052            # BVN (default)
 *   php artisan arewasmart:test 12345678901 --service=nin
 *   php artisan arewasmart:test 22211960052 --base=https://.../api/v1
 *
 * Reads services.arewasmart.{base_url,token} (env AREWASMART_VERIFY_*).
 * Bearer auth; POST {base}/{service}/verify with body { <service>: value }.
 * Charges nothing and stores nothing.
 */
class TestArewasmartVerify extends Command
{
    protected $signature = 'arewasmart:test
        {value : The BVN/NIN value to verify}
        {--service=bvn : Service segment (bvn or nin)}
        {--base= : Override the base URL for this call}';

    protected $description = 'Send a test request to the ArewaSmart verification API and dump the raw response';

    public function handle(): int
    {
        $base = rtrim((string) ($this->option('base') ?: config('services.arewasmart.base_url')), '/');
        $token = (string) config('services.arewasmart.token');
        $service = trim((string) $this->option('service'), '/');
        $value = (string) $this->argument('value');

        if ($token === '') {
            $this->error('AREWASMART_VERIFY_TOKEN is not set in your .env.');

            return self::FAILURE;
        }

        $endpoint = "{$base}/{$service}/verify";
        $payload = [$service => $value]; // { "bvn": "..." } or { "nin": "..." }

        $this->line('POST '.$endpoint);
        $this->line('Authorization: Bearer '.substr($token, 0, 8).'…');
        $this->line('Body: '.json_encode($payload));
        $this->newLine();

        try {
            $response = Http::timeout(40)
                ->withToken($token)        // Authorization: Bearer <token>
                ->acceptJson()
                ->post($endpoint, $payload);
        } catch (\Throwable $e) {
            $this->error('Request failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('HTTP status: '.$response->status());
        $this->newLine();

        $json = $response->json();
        if (is_array($json)) {
            $this->line(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->warn('Response is not valid JSON. Raw body (first 1KB):');
            $this->line(substr($response->body(), 0, 1024));
        }

        return self::SUCCESS;
    }
}
