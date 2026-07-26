<?php

use App\Models\DataSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Settle ambiguous data purchases. The cadence is data_settings
// requery_interval_seconds (admin: Data > Routing & Settings), NOT a constant:
// a reseller upstream confirms delivery within seconds, and a fixed five-minute
// loop left buyers watching a spinner long after the data had landed.
//
// Sub-minute intervals use repeatEvery(), which keeps `schedule:run` alive for
// the remainder of the minute and re-checks -- so cron still only needs its
// usual once-a-minute entry.
//
// Wrapped because this runs on EVERY artisan invocation: a database that is
// down or not yet migrated must not make the console unusable.
try {
    $requerySeconds = DataSetting::requeryIntervalSeconds();
} catch (Throwable) {
    $requerySeconds = 300;
}

$reconcile = Schedule::command('data:reconcile');

if ($requerySeconds < 60) {
    // Laravel exposes only a fixed set of sub-minute helpers (repeatEvery is
    // protected), so the configured value snaps DOWN to the nearest supported
    // step -- never up, or the buyer would wait longer than the admin asked.
    $steps = [
        30 => 'everyThirtySeconds',
        20 => 'everyTwentySeconds',
        15 => 'everyFifteenSeconds',
        10 => 'everyTenSeconds',
        5 => 'everyFiveSeconds',
        2 => 'everyTwoSeconds',
        1 => 'everySecond',
    ];

    foreach ($steps as $seconds => $method) {
        if ($requerySeconds >= $seconds) {
            $reconcile->{$method}();
            break;
        }
    }
} else {
    $reconcile->cron('*/'.max(1, min(59, intdiv($requerySeconds, 60))).' * * * *');
}

$reconcile->withoutOverlapping();

// Daily data-module ledger integrity check.
Schedule::command('data:ledger-check')->dailyAt('02:00');

// Trim the verification provider-call audit to its retention window. That table
// gains a row per failover hop, so it outgrows everything else without this.
Schedule::command('verification:prune-attempts')->dailyAt('03:00');
