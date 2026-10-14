<?php

use App\Models\Plan;
use Illuminate\Database\QueryException;

test('orderedByTier sorts by tier, not by id or insertion order', function () {
    // Simulates exactly what happened when Solo was added to a database
    // that already had free/start-up/pro seeded: it gets whatever id is
    // next (highest), which has nothing to do with where it belongs
    // size-wise.
    Plan::factory()->create(['key' => 'pro']);
    Plan::factory()->create(['key' => 'free']);
    Plan::factory()->create(['key' => 'start-up']);
    Plan::factory()->create(['key' => 'solo']);

    expect(Plan::orderedByTier()->pluck('key')->all())
        ->toBe(['free', 'solo', 'start-up', 'pro']);
});

test('orderedByTier simply omits a tier that has not been seeded yet', function () {
    Plan::factory()->create(['key' => 'free']);
    Plan::factory()->create(['key' => 'pro']);

    expect(Plan::orderedByTier()->pluck('key')->all())->toBe(['free', 'pro']);
});

test('orderedByTier follows the position column, not the tier key', function () {
    Plan::factory()->create(['key' => 'free', 'position' => 5]);
    Plan::factory()->create(['key' => 'pro', 'position' => 1]);

    expect(Plan::orderedByTier()->pluck('key')->all())->toBe(['pro', 'free']);
});

test('only one current version of a tier can exist', function () {
    Plan::factory()->create(['key' => 'pro']);

    expect(fn () => Plan::factory()->create(['key' => 'pro', 'version' => 2]))
        ->toThrow(QueryException::class);
});

test('an expired or archived plan is not offered but still exists', function () {
    $expired = Plan::factory()->create(['key' => 'a', 'expires_on' => now()->toDateString()]);
    $archived = Plan::factory()->create(['key' => 'b']);
    $archived->forceFill(['superseded_at' => now()])->save();
    $open = Plan::factory()->create(['key' => 'c', 'expires_on' => now()->addDay()->toDateString()]);

    expect(Plan::orderedByTier()->pluck('key')->all())->toBe(['c'])
        ->and($expired->isOffered())->toBeFalse()
        ->and($archived->isOffered())->toBeFalse()
        ->and($open->isOffered())->toBeTrue();
});
