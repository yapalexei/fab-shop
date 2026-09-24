<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly rather than daily: trials end at midnight on each shop's own
// calendar, so a once-a-day UTC run would notify some shops most of a day late.
Schedule::command('app:notify-expired-trials')->hourly();
