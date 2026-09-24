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
