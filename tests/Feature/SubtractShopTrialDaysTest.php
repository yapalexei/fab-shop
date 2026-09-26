<?php

use App\Models\Shop;
use Illuminate\Support\Carbon;

test('subtracts the given number of days from a shop\'s trial_ends_at', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => Carbon::parse('2026-10-08 00:00:00')]);

    $this->artisan('shop:subtract-trial-days', ['shop_id' => $shop->id, 'days' => 5])
        ->assertExitCode(0);

    expect($shop->fresh()->trial_ends_at->toDateString())->toBe('2026-10-03');
});

test('fails cleanly for an unknown shop id', function () {
    $this->artisan('shop:subtract-trial-days', ['shop_id' => 999999, 'days' => 5])
        ->assertExitCode(1);
});

test('fails cleanly for a shop with no trial_ends_at to subtract from', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => null]);

    $this->artisan('shop:subtract-trial-days', ['shop_id' => $shop->id, 'days' => 5])
        ->assertExitCode(1);
});

test('rejects a non-positive day count', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->addDays(10)]);

    $this->artisan('shop:subtract-trial-days', ['shop_id' => $shop->id, 'days' => 0])
        ->assertExitCode(1);

    expect($shop->fresh()->trial_ends_at->isToday())->toBeFalse();
});

test('refuses to run in production', function () {
    $original = app()['env'];
    app()['env'] = 'production';
    $shop = Shop::factory()->create(['trial_ends_at' => now()->addDays(10)]);
    $before = $shop->trial_ends_at;

    $this->artisan('shop:subtract-trial-days', ['shop_id' => $shop->id, 'days' => 5])
        ->assertExitCode(1);

    expect($shop->fresh()->trial_ends_at->equalTo($before))->toBeTrue();

    app()['env'] = $original;
});
