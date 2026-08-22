<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the analytics table bounded (see config/analytics.php).
Schedule::command('page-views:prune')->dailyAt('03:15');

// Crawlers never reach the page-view beacon, so they are counted from the
// nginx log instead. Hourly rather than daily: logrotate discards the live
// log at midnight, and re-reading whole days makes a repeat run a no-op, so
// frequent passes cost little and a missed one loses nothing.
Schedule::command('analytics:parse-logs')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
