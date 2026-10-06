<?php

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\DB;

test('defaultTrialDays lazily creates the one row with the historical default', function () {
    expect(DB::table('platform_settings')->count())->toBe(0);

    expect(PlatformSetting::defaultTrialDays())->toBe(30);
    expect(DB::table('platform_settings')->count())->toBe(1);
});

test('defaultTrialDays is cached - a write that bypasses the cache does not change it until forgetCache is called', function () {
    expect(PlatformSetting::defaultTrialDays())->toBe(30); // warms the cache

    // Bypasses PlatformSetting::forgetCache() on purpose, to prove the
    // cached value really is stale until something invalidates it.
    DB::table('platform_settings')->update(['default_trial_days' => 30]);

    expect(PlatformSetting::defaultTrialDays())->toBe(30);

    PlatformSetting::forgetCache();

    expect(PlatformSetting::defaultTrialDays())->toBe(30);
});

test('current() is never cached - always reflects the latest row', function () {
    PlatformSetting::defaultTrialDays(); // warms the defaultTrialDays cache

    DB::table('platform_settings')->update(['default_trial_days' => 45]);

    expect(PlatformSetting::current()->default_trial_days)->toBe(45);
});
