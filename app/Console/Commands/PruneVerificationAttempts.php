<?php

namespace App\Console\Commands;

use App\Models\VerificationAttempt;
use App\Models\VerificationSetting;
use Illuminate\Console\Command;

/**
 * Trims the provider-call audit to the retention window set in
 * Admin > Verification > Routing & Failover.
 *
 * The table grows by one row per hop of every verification, so on a busy day it
 * outpaces every other table in the app. Without this the retention setting
 * would be decorative and the log would grow without bound.
 */
class PruneVerificationAttempts extends Command
{
    protected $signature = 'verification:prune-attempts {--days= : Override the configured retention window}';

    protected $description = 'Delete verification provider-call logs older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: VerificationSetting::int('attempt_retention_days', 30));

        if ($days < 1) {
            $this->warn('Retention window is under a day; nothing pruned.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        // Chunked so a long-neglected table cannot lock the log for minutes or
        // blow out memory on a single enormous DELETE.
        $deleted = 0;
        do {
            $batch = VerificationAttempt::where('created_at', '<', $cutoff)->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Pruned {$deleted} verification attempt(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
