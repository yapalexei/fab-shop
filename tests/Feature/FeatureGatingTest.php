<?php

use App\Enums\Feature;
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
