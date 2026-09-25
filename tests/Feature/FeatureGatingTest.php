<?php

use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\Shop;

test('a shop on trial has every feature regardless of plan', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->addDays(14), 'plan_id' => null]);

    foreach (Feature::cases() as $feature) {
        expect($shop->hasFeature($feature))->toBeTrue();
    }
});

test('a shop past trial with no plan has no features', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => null]);

    expect($shop->hasFeature(Feature::Customers))->toBeFalse();
});

test('a shop past trial only has its plan\'s features', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::Customers, Feature::QuoteBuilder]);

    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    expect($shop->hasFeature(Feature::Customers))->toBeTrue()
        ->and($shop->hasFeature(Feature::QuoteBuilder))->toBeTrue()
        ->and($shop->hasFeature(Feature::JobBoard))->toBeFalse();
});

test('a locked feature route 403s once the trial has ended and the plan lacks it', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);

    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
});

test('enabledFeatures is shared with the frontend and reflects trial access', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->addDays(14), 'plan_id' => null]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('customers.index'))
        ->assertInertia(fn ($page) => $page->where('enabledFeatures', fn ($features) => collect($features)->contains('customers')));
});

test('a feature with a longer admin-set trial stays free after the shop\'s default trial ends', function () {
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->update(['trial_days' => 30]);

    // trial_ends_at is 14 days from signup (the default) and has already
    // passed - but MaterialTracking's own trial runs to day 30, so it's
    // still within its own window 20 days after trial_ends_at.
    $shop = Shop::factory()->create([
        'trial_started_at' => now()->subDays(20),
        'trial_ends_at' => now()->subDays(6),
        'plan_id' => null,
    ]);

    expect($shop->hasFeature(Feature::MaterialTracking))->toBeTrue()
        ->and($shop->hasFeature(Feature::Customers))->toBeFalse();
});

test('a feature with a shorter admin-set trial locks before the shop\'s default trial ends', function () {
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::QualificationRecords->value)->update(['trial_days' => 3]);

    // QualificationRecords' own trial (3 days from signup) already lapsed,
    // even though the shop's overall 14-day trial_ends_at is still future.
    $shop = Shop::factory()->create([
        'trial_started_at' => now()->subDays(5),
        'trial_ends_at' => now()->addDays(9),
        'plan_id' => null,
    ]);

    expect($shop->hasFeature(Feature::QualificationRecords))->toBeFalse()
        ->and($shop->hasFeature(Feature::Customers))->toBeTrue();
});

test('enabledFeatures reflects a per-feature trial extension past the default trial window', function () {
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->update(['trial_days' => 30]);

    $shop = Shop::factory()->create([
        'trial_started_at' => now()->subDays(20),
        'trial_ends_at' => now()->subDays(6),
        'plan_id' => null,
    ]);

    expect($shop->enabledFeatures())->toContain(Feature::MaterialTracking)
        ->and($shop->enabledFeatures())->not->toContain(Feature::Customers);
});

test('a shop with no trial at all gets no feature from onTrialFor regardless of trial_days', function () {
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->update(['trial_days' => 365]);

    $shop = Shop::factory()->create(['trial_started_at' => null, 'trial_ends_at' => null, 'plan_id' => null]);

    expect($shop->hasFeature(Feature::MaterialTracking))->toBeFalse();
});
