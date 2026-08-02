<?php

namespace App\Console\Commands;

use App\Models\VerificationAttempt;
use Illuminate\Console\Command;

/**
 * Print the exact request the app sent to a provider and the exact reply it got.
 *
 * Every provider call already writes a `verification_attempts` row, credentials
 * stripped, so a misconfigured endpoint can be diagnosed from what actually went
 * over the wire rather than from what the admin screen appears to say. That
 * distinction is the whole point: a field map row and a constant field look
 * almost identical in the form and produce completely different request bodies.
 *
 * Header *values* are never stored, only their names — so this is safe to paste
 * into a bug report.
 */
class ShowVerificationCalls extends Command
{
    protected $signature = 'verify:calls
        {--service= : Filter by service key, e.g. nin.ipe or nin.validation.status}
        {--provider= : Filter by provider name, e.g. ArewaSmart}
        {--outcome= : Filter by outcome: success, fail or timeout}
        {--limit=3 : How many of the most recent calls to show}';

    protected $description = 'Show what the app sent to a verification provider and what it received back';

    public function handle(): int
    {
        $query = VerificationAttempt::query()->latest('created_at');

        if ($service = $this->option('service')) {
            $query->where('service', $service);
        }

        if ($provider = $this->option('provider')) {
            $query->where('provider_name', 'like', "%{$provider}%");
        }

        if ($outcome = $this->option('outcome')) {
            $query->where('outcome', $outcome);
        }

        $attempts = $query->limit(max(1, (int) $this->option('limit')))->get();

        if ($attempts->isEmpty()) {
            $this->warn('No provider calls recorded for those filters.');
            $this->line('');
            $this->line('If you expected one, the request may have been rejected before it reached');
            $this->line('a provider — an unpriced service, an empty wallet, or no routed provider.');
            $this->line('Run without --service to see the most recent calls of any kind.');

            return self::SUCCESS;
        }

        foreach ($attempts as $attempt) {
            $this->render($attempt);
        }

        return self::SUCCESS;
    }

    private function render(VerificationAttempt $attempt): void
    {
        $request = (array) $attempt->request_payload;

        $this->line('');
        $this->line(str_repeat('=', 78));
        $this->line(sprintf(
            '%s  %s  via %s  →  %s (HTTP %s) in %dms',
            $attempt->created_at?->format('Y-m-d H:i:s'),
            $attempt->service,
            $attempt->provider_name ?? 'unknown provider',
            strtoupper($attempt->outcome),
            $attempt->http_status ?? '—',
            $attempt->duration_ms,
        ));
        $this->line(str_repeat('=', 78));

        if ($attempt->message) {
            $this->line('Message: '.$attempt->message);
        }

        $this->line('');
        $this->info('--- SENT ---');
        $this->line(($request['method'] ?? '?').' '.($request['url'] ?? '?'));

        $query = (array) ($request['query'] ?? []);
        $body = (array) ($request['body'] ?? []);

        $this->line('Header names: '.(implode(', ', (array) ($request['headers'] ?? [])) ?: '(none)'));
        $this->line('Query: '.($query === [] ? '(none)' : $this->json($query)));
        $this->line('Body:  '.($body === [] ? '(none)' : $this->json($body)));

        $this->line('');
        $this->info('--- RECEIVED ---');
        $this->line($this->json((array) $attempt->response));
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
