<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Settle ambiguous data purchases (default cadence = data_settings
// requery_interval_minutes, 5). Requires `php artisan schedule:work` running.
Schedule::command('data:reconcile')->everyFiveMinutes()->withoutOverlapping();

// Daily data-module ledger integrity check.
Schedule::command('data:ledger-check')->dailyAt('02:00');

// Trim the verification provider-call audit to its retention window. That table
// gains a row per failover hop, so it outgrows everything else without this.
Schedule::command('verification:prune-attempts')->dailyAt('03:00');
