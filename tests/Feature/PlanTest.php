<?php

use App\Models\Plan;

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
