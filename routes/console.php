<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly rather than daily: a trial ends 14 x 24h after signup, at whatever
// time of day that was, so a once-a-day run would notify up to a day late.
Schedule::command('app:notify-expired-trials')->hourly();

// A shop's owner-requested deletion runs once its grace period has ended;
// hourly so it isn't up to a day late.
Schedule::command('app:purge-deleted-shops')->hourly();

Schedule::command('app:prune-data-exports')->daily();

// A tier price drop reaches subscribers when their paid period ends.
Schedule::command('app:apply-scheduled-plan-changes')->hourly();

// A feature we can no longer support: stop add-on billing and tell the owners.
Schedule::command('app:announce-discontinued-features')->daily();
