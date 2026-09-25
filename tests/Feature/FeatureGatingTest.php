<?php

use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;

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

test('an active add-on subscription grants a feature the plan does not include', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::Customers]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    expect($shop->hasFeature(Feature::MaterialTracking))->toBeFalse();

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $shop->id,
        'feature' => Feature::MaterialTracking->value,
        'started_at' => now(),
    ])->save();

    expect($shop->hasFeature(Feature::MaterialTracking))->toBeTrue()
        ->and($shop->enabledFeatures())->toContain(Feature::MaterialTracking);
});

test('a cancelled add-on subscription no longer grants the feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $shop->id,
        'feature' => Feature::QualificationRecords->value,
        'started_at' => now()->subMonth(),
        'cancelled_at' => now(),
    ])->save();

    expect($shop->hasFeature(Feature::QualificationRecords))->toBeFalse();
});

test('an add-on subscription for an already-bundled feature does not duplicate it in enabledFeatures', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::Customers]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $shop->id,
        'feature' => Feature::Customers->value,
        'started_at' => now(),
    ])->save();

    expect($shop->enabledFeatures()->filter(fn (Feature $f) => $f === Feature::Customers))->toHaveCount(1);
});

test('hasFeature checks the shop passed in, not the authenticated user\'s current shop', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);

    $otherShop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $targetShop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $targetShop->id,
        'feature' => Feature::MaterialTracking->value,
        'started_at' => now(),
    ])->save();

    // Authenticated user's *current* shop is $otherShop, not $targetShop -
    // the BelongsToShop global scope would wrongly filter by $otherShop's
    // id if Shop::hasActiveFeatureSubscription() didn't bypass it.
    $user = actingAsShopMember($otherShop);
    $this->actingAs($user);

    expect($targetShop->hasFeature(Feature::MaterialTracking))->toBeTrue();
});
