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

        // Job board: allow dragging cards between columns (every move still
        // asks for confirmation). Off unless a shop turns it on.
        'job_board_drag_enabled' => (bool) env('SHOP_DEFAULT_JOB_BOARD_DRAG_ENABLED', false),
    ],

    // Every shop gets the built-in material types (App\Support\MaterialTypes)
    // and may add this many of its own on top.
    'max_custom_material_types' => (int) env('SHOP_MAX_CUSTOM_MATERIAL_TYPES', 20),

    // Most extra seats one shop can buy on top of its plan.
    'max_extra_seats' => 20,

    // Annual billing: a year is charged as this many months of the monthly
    // price (10 = two months free). Placeholder until pricing is decided.
    'annual_months_charged' => (int) env('SHOP_ANNUAL_MONTHS_CHARGED', 10),

    // How long a team invite (onboarding step 2, or Team > Invite) stays
    // acceptable before it needs to be re-sent.
    'invitation_expiry_days' => (int) env('SHOP_INVITATION_EXPIRY_DAYS', 7),

    // After the owner asks to delete the shop, everything stays (and the
    // request can be cancelled) for this many days; then it is erased.
    'deletion_grace_days' => (int) env('SHOP_DELETION_GRACE_DAYS', 14),

    // How long a finished data export stays downloadable before it is removed.
    'export_retention_days' => (int) env('SHOP_EXPORT_RETENTION_DAYS', 7),

];
