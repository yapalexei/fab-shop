<?php

use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Shop;
use Database\Seeders\PlanSeeder;

test('a non-platform-admin cannot reach plan settings', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->get(route('platform-admin.plans.index'))->assertForbidden();
});

test('plan settings lists every seeded plan with its seats and bundled features', function () {
    $this->seed(PlanSeeder::class);
    $admin = actingAsPlatformAdmin();

    $this->actingAs($admin)->get(route('platform-admin.plans.index'))
        ->assertInertia(fn ($page) => $page
            ->component('PlatformAdmin/Plans')
            ->has('plans', 4)
            ->where('plans.0.key', 'free')
            ->where('plans.3.key', 'pro')
            ->has('allFeatures', count(Feature::cases())));
});

test('a platform admin can change a plan\'s default seat count', function () {
    $admin = actingAsPlatformAdmin();
    $plan = Plan::factory()->create(['user_limit' => 5, 'key' => 'start-up']);

    $this->actingAs($admin)->patch(route('platform-admin.plans.update'), [
        'plans' => [
            ['id' => $plan->id, 'user_limit' => 15, 'features' => []],
        ],
    ])->assertRedirect(route('platform-admin.plans.index'));

    expect($plan->fresh()->user_limit)->toBe(15);
});

test('a platform admin can clear a plan\'s seat count to unlimited', function () {
    $admin = actingAsPlatformAdmin();
    $plan = Plan::factory()->create(['user_limit' => 5]);

    $this->actingAs($admin)->patch(route('platform-admin.plans.update'), [
        'plans' => [
            ['id' => $plan->id, 'user_limit' => null, 'features' => []],
        ],
    ])->assertRedirect();

    expect($plan->fresh()->user_limit)->toBeNull();
});

test('a platform admin can change which features a plan bundles', function () {
    $admin = actingAsPlatformAdmin();
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::Customers]);

    $this->actingAs($admin)->patch(route('platform-admin.plans.update'), [
        'plans' => [
            [
                'id' => $plan->id,
                'user_limit' => $plan->user_limit,
                'features' => [Feature::JobBoard->value, Feature::QuoteBuilder->value],
            ],
        ],
    ])->assertRedirect();

    expect($plan->features())->toHaveCount(2)
        ->and($plan->hasFeature(Feature::JobBoard))->toBeTrue()
        ->and($plan->hasFeature(Feature::QuoteBuilder))->toBeTrue()
        ->and($plan->hasFeature(Feature::Customers))->toBeFalse();
});

test('updating one plan\'s features does not touch another plan\'s features', function () {
    $admin = actingAsPlatformAdmin();
    $planA = Plan::factory()->create(['key' => 'solo']);
    $planB = Plan::factory()->create(['key' => 'pro']);
    $planA->syncFeatures([Feature::Customers]);
    $planB->syncFeatures([Feature::Customers, Feature::JobBoard]);

    $this->actingAs($admin)->patch(route('platform-admin.plans.update'), [
        'plans' => [
            ['id' => $planA->id, 'user_limit' => $planA->user_limit, 'features' => []],
        ],
    ])->assertRedirect();

    expect($planA->features())->toHaveCount(0)
        ->and($planB->features())->toHaveCount(2);
});

test('a non-platform-admin cannot update plan settings', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $plan = Plan::factory()->create(['user_limit' => 5]);

    $this->actingAs($owner)->patch(route('platform-admin.plans.update'), [
        'plans' => [
            ['id' => $plan->id, 'user_limit' => 99, 'features' => []],
        ],
    ])->assertForbidden();

    expect($plan->fresh()->user_limit)->toBe(5);
});
