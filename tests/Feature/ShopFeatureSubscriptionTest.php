<?php

use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;
use App\Support\PlanAdvisor;
use Database\Seeders\PlanSeeder;

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

function seededTiers(): void
{
    test()->seed(PlanSeeder::class);
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->whereIn('feature', [Feature::Customers->value, Feature::QuoteBuilder->value])->update(['price_cents' => 2000]);
    // Test shops have an owner plus the acting user; Solo's single seat wouldn't fit them.
    Plan::where('key', 'solo')->update(['user_limit' => 5]);
}

test('subscribing to an add-on a cheaper plan covers is stopped until confirmed', function () {
    seededTiers();
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => Plan::where('key', 'free')->value('id')]);
    $owner = actingAsShopMember($shop, 'Owner');
    ShopFeatureSubscription::query()->forceCreate(['shop_id' => $shop->id, 'feature' => Feature::Customers->value, 'started_at' => now()]);

    // Customers + Quote builder = $40 as add-ons; Solo has both for $29.
    $this->actingAs($owner)->post(route('shop.addons.store', Feature::QuoteBuilder->value))
        ->assertSessionHas('error');
    expect($shop->fresh()->hasActiveFeatureSubscription(Feature::QuoteBuilder))->toBeFalse();

    $this->actingAs($owner)->post(route('shop.addons.store', Feature::QuoteBuilder->value), ['confirm_separate' => true])
        ->assertSessionMissing('error');
    expect($shop->fresh()->hasActiveFeatureSubscription(Feature::QuoteBuilder))->toBeTrue();
});

test('the add-ons page carries per-add-on advice and a banner for existing add-ons', function () {
    seededTiers();
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => Plan::where('key', 'free')->value('id')]);
    $owner = actingAsShopMember($shop, 'Owner');
    foreach ([Feature::Customers, Feature::QuoteBuilder] as $feature) {
        ShopFeatureSubscription::query()->forceCreate(['shop_id' => $shop->id, 'feature' => $feature->value, 'started_at' => now()]);
    }

    $this->actingAs($owner)->get(route('shop.addons.index'))->assertInertia(fn ($page) => $page
        ->where('bannerAdvice.plan_key', 'solo')
        ->where('bannerAdvice.savings_cents', 1100));
});

test('a plan that costs the same but includes more still triggers the warning', function () {
    seededTiers();
    FeatureSetting::query()->where('feature', Feature::QuoteBuilder->value)->update(['price_cents' => 3900]);
    Plan::where('key', 'solo')->update(['monthly_price_cents' => 9900]); // take Solo out of the running
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => Plan::where('key', 'free')->value('id')]);
    $owner = actingAsShopMember($shop, 'Owner');
    ShopFeatureSubscription::query()->forceCreate(['shop_id' => $shop->id, 'feature' => Feature::Customers->value, 'started_at' => now()]);

    // 20 + 39 = $59 as add-ons; Shop is $59 and adds the Job board.
    $this->actingAs($owner)->post(route('shop.addons.store', Feature::QuoteBuilder->value))
        ->assertSessionHas('error');
    $advice = PlanAdvisor::forNewAddon($shop->fresh(), Feature::QuoteBuilder);
    expect($advice['plan_key'])->toBe('start-up')
        ->and($advice['savings_cents'])->toBe(0)
        ->and($advice['extras'])->toContain('Job board');
});

test('no advice when subscribing separately is the best deal', function () {
    seededTiers();
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => Plan::where('key', 'pro')->value('id')]);
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->get(route('shop.addons.index'))->assertInertia(fn ($page) => $page->where('bannerAdvice', null));
});
