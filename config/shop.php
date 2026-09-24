<?php

/*
|--------------------------------------------------------------------------
| Shop setting defaults
|--------------------------------------------------------------------------
|
| Global fallbacks for every per-shop setting. A shop's own column stays
| null until its owner (or someone they've granted "manage shop settings")
| changes it, so changing a default here applies to every shop that never
| customised that setting - nothing needs configuring out of the box.
|
*/

return [

    'defaults' => [
        // IANA timezone name, not a fixed offset, so daylight saving shifts
        // are handled. New shops pick up the signing-up browser's timezone;
        // this only applies to shops that never had one recorded.
        'timezone' => env('SHOP_DEFAULT_TIMEZONE', 'UTC'),

        // Dashboard: a sent quote with no won/lost decision after this many
        // days is flagged for a follow-up.
        'stale_quote_days' => (int) env('SHOP_DEFAULT_STALE_QUOTE_DAYS', 7),

        // Dashboard: how far back the quote win rate looks.
        'win_rate_window_days' => (int) env('SHOP_DEFAULT_WIN_RATE_WINDOW_DAYS', 90),
    ],

];
