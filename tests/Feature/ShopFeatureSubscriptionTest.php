<?php

use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;

test('the owner can subscribe to a feature not in their plan', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::Customers]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->post(route('shop.addons.store', Feature::MaterialTracking->value))
        ->assertRedirect();

    expect($shop->fresh()->hasFeature(Feature::MaterialTracking))->toBeTrue();
});

test('staff cannot subscribe to an add-on', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($staff)->post(route('shop.addons.store', Feature::MaterialTracking->value))
        ->assertForbidden();
});

test('subscribing to a feature already bundled in the plan is refused', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::MaterialTracking]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->post(route('shop.addons.store', Feature::MaterialTracking->value))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(ShopFeatureSubscription::query()->count())->toBe(0);
});

test('the owner can cancel an active add-on', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $owner = actingAsShopMember($shop, 'Owner');

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $shop->id,
        'feature' => Feature::MaterialTracking->value,
        'started_at' => now(),
    ])->save();

    $this->actingAs($owner)->delete(route('shop.addons.destroy', Feature::MaterialTracking->value))
        ->assertRedirect();

    expect($shop->fresh()->hasFeature(Feature::MaterialTracking))->toBeFalse();
});

test('an add-on subscription is scoped per shop', function () {
    $planA = Plan::factory()->create();
    $planA->syncFeatures([]);
    $shopA = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $planA->id]);
    $shopB = Shop::factory()->create(['trial_ends_at' => now()->subDay()]);

    $ownerA = actingAsShopMember($shopA, 'Owner');
    $ownerB = actingAsShopMember($shopB, 'Owner');

    $this->actingAs($ownerA)->post(route('shop.addons.store', Feature::MaterialTracking->value));

    expect($shopA->fresh()->hasFeature(Feature::MaterialTracking))->toBeTrue()
        ->and($shopB->fresh()->hasFeature(Feature::MaterialTracking))->toBeFalse();

    // Owner B cancelling never touches shop A's row, even though both
    // shops now share the same feature key.
    $this->actingAs($ownerB)->delete(route('shop.addons.destroy', Feature::MaterialTracking->value));

    expect($shopA->fresh()->hasFeature(Feature::MaterialTracking))->toBeTrue();
});

test('the add-ons index shows price, bundled and subscribed state per feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::Customers]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $owner = actingAsShopMember($shop, 'Owner');

    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->update(['price_cents' => 1500]);

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $shop->id,
        'feature' => Feature::MaterialTracking->value,
        'started_at' => now(),
    ])->save();

    $this->actingAs($owner)->get(route('shop.addons.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Addons/Index')
            ->where('addons', function ($addons) {
                $addons = collect($addons);
                $customers = $addons->firstWhere('feature', Feature::Customers->value);
                $materialTracking = $addons->firstWhere('feature', Feature::MaterialTracking->value);

                return $customers['bundledInPlan'] === true
                    && $materialTracking['bundledInPlan'] === false
                    && $materialTracking['subscribed'] === true
                    && $materialTracking['priceCents'] === 1500;
            })
        );
});
