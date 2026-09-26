<?php

use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;
use App\Models\User;

test('a non-platform-admin cannot reach the platform admin dashboard', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->get(route('platform-admin.index'))->assertForbidden();
});

test('a platform admin sees shops across every tenant, not just their own', function () {
    $admin = actingAsPlatformAdmin();
    $shopA = Shop::factory()->create(['name' => 'Ironclad Fabrication', 'created_at' => now()->subMinute()]);
    $shopB = Shop::factory()->create(['name' => 'Riverside Welding', 'created_at' => now()]);

    $response = $this->actingAs($admin)->get(route('platform-admin.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('PlatformAdmin/Index')
        ->has('shops.data', 2)
        ->where('shops.data.0.name', $shopB->name)
        ->where('shops.data.1.name', $shopA->name)
    );
});

test('the shop directory paginates at 30 per page', function () {
    $admin = actingAsPlatformAdmin();
    Shop::factory()->count(35)->create();

    $this->actingAs($admin)->get(route('platform-admin.index'))
        ->assertInertia(fn ($page) => $page
            ->has('shops.data', 30)
            ->where('shops.total', 35));

    $this->actingAs($admin)->get(route('platform-admin.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page->has('shops.data', 5));
});

test('the shop directory can be searched by shop name or owner email', function () {
    $admin = actingAsPlatformAdmin();
    Shop::factory()->create(['name' => 'Ironclad Fabrication']);
    $owner = User::factory()->create(['email' => 'owner@riverside-welding.test']);
    Shop::factory()->create(['name' => 'Riverside Welding', 'owner_id' => $owner->id]);

    $this->actingAs($admin)->get(route('platform-admin.index', ['search' => 'Ironclad']))
        ->assertInertia(fn ($page) => $page
            ->has('shops.data', 1)
            ->where('shops.data.0.name', 'Ironclad Fabrication'));

    $this->actingAs($admin)->get(route('platform-admin.index', ['search' => 'riverside-welding.test']))
        ->assertInertia(fn ($page) => $page
            ->has('shops.data', 1)
            ->where('shops.data.0.name', 'Riverside Welding'));
});

test('the shop directory can be filtered by status', function () {
    $admin = actingAsPlatformAdmin();
    $plan = Plan::factory()->create();
    $suspended = Shop::factory()->create(['name' => 'Suspended Shop', 'suspended_at' => now()]);
    $onboarding = Shop::factory()->create(['name' => 'Onboarding Shop', 'onboarded_at' => null]);
    $trialing = Shop::factory()->create(['name' => 'Trialing Shop', 'trial_ends_at' => now()->addDays(5)]);
    $active = Shop::factory()->create(['name' => 'Active Shop', 'trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    $this->actingAs($admin)->get(route('platform-admin.index', ['status' => 'suspended']))
        ->assertInertia(fn ($page) => $page->has('shops.data', 1)->where('shops.data.0.name', $suspended->name));

    $this->actingAs($admin)->get(route('platform-admin.index', ['status' => 'onboarding']))
        ->assertInertia(fn ($page) => $page->has('shops.data', 1)->where('shops.data.0.name', $onboarding->name));

    $this->actingAs($admin)->get(route('platform-admin.index', ['status' => 'trial']))
        ->assertInertia(fn ($page) => $page->has('shops.data', 1)->where('shops.data.0.name', $trialing->name));

    $this->actingAs($admin)->get(route('platform-admin.index', ['status' => 'active']))
        ->assertInertia(fn ($page) => $page->has('shops.data', 1)->where('shops.data.0.name', $active->name));
});

test('the shop directory can be filtered by plan, including "no plan"', function () {
    $admin = actingAsPlatformAdmin();
    $plan = Plan::factory()->create();
    $onPlan = Shop::factory()->create(['plan_id' => $plan->id]);
    $noPlan = Shop::factory()->create(['plan_id' => null]);

    $this->actingAs($admin)->get(route('platform-admin.index', ['plan_id' => $plan->id]))
        ->assertInertia(fn ($page) => $page->has('shops.data', 1)->where('shops.data.0.id', $onPlan->id));

    $this->actingAs($admin)->get(route('platform-admin.index', ['plan_id' => 'none']))
        ->assertInertia(fn ($page) => $page->has('shops.data', 1)->where('shops.data.0.id', $noPlan->id));
});

test('the shop directory can be filtered by trial days remaining, including already-lapsed trials', function () {
    $admin = actingAsPlatformAdmin();
    Shop::factory()->create(['name' => 'Ending Soon', 'trial_ends_at' => now()->addDays(3)]);
    Shop::factory()->create(['name' => 'Already Lapsed', 'trial_ends_at' => now()->subDays(2)]);
    Shop::factory()->create(['name' => 'Plenty Left', 'trial_ends_at' => now()->addDays(20)]);

    $this->actingAs($admin)->get(route('platform-admin.index', ['trial_within_days' => 7]))
        ->assertInertia(fn ($page) => $page
            ->has('shops.data', 2)
            ->where('shops.data', fn ($data) => collect($data)->pluck('name')
                ->diff(['Ending Soon', 'Already Lapsed'])->isEmpty()));
});

test('revenue totals stay platform-wide even when the shop list is filtered', function () {
    $admin = actingAsPlatformAdmin();
    $plan = Plan::factory()->create(['monthly_price_cents' => 4900]);
    Shop::factory()->create(['name' => 'Match Me', 'plan_id' => $plan->id, 'trial_ends_at' => now()->subDay()]);
    Shop::factory()->create(['name' => 'Filtered Out', 'plan_id' => $plan->id, 'trial_ends_at' => now()->subDay()]);

    $this->actingAs($admin)->get(route('platform-admin.index', ['search' => 'Match Me']))
        ->assertInertia(fn ($page) => $page
            ->has('shops.data', 1)
            ->where('revenue.active_mrr_cents', 9800));
});

test('a platform admin can suspend a shop, which locks out its members', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create();
    $member = actingAsShopMember($shop);

    $this->actingAs($admin)->post(route('platform-admin.shops.suspend', $shop))
        ->assertRedirect();

    expect($shop->fresh()->isSuspended())->toBeTrue();

    $this->actingAs($member)->get(route('dashboard'))
        ->assertRedirect(route('shop.suspended'));

    $this->actingAs($member)->get(route('shop.suspended'))
        ->assertInertia(fn ($page) => $page
            ->component('ShopSuspended')
            ->where('shopName', $shop->name)
        );
});

test('unsuspending a shop restores its members access', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create(['suspended_at' => now()]);
    $member = actingAsShopMember($shop);

    $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('shop.suspended'));

    $this->actingAs($admin)->post(route('platform-admin.shops.unsuspend', $shop))
        ->assertRedirect();

    expect($shop->fresh()->isSuspended())->toBeFalse();

    // $member's currentShop relation was already loaded by the request
    // above and the test's auth guard holds this same instance across
    // calls - refresh it so the second request doesn't see the stale,
    // still-suspended relation.
    $member->refresh();

    $this->actingAs($member)->get(route('dashboard'))->assertOk();
});

test('visiting the suspended notice for a shop that is not suspended redirects to the dashboard', function () {
    $shop = Shop::factory()->create();
    $member = actingAsShopMember($shop);

    $this->actingAs($member)->get(route('shop.suspended'))->assertRedirect(route('dashboard'));
});

test('a platform admin can change a shop\'s plan', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create();
    $plan = Plan::factory()->create(['key' => 'pro']);

    $this->actingAs($admin)->patch(route('platform-admin.shops.plan', $shop), [
        'plan_id' => $plan->id,
    ])->assertRedirect();

    expect($shop->fresh()->plan_id)->toBe($plan->id);
});

test('changing a shop\'s plan cancels an add-on subscription the new plan already bundles', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create(['plan_id' => null]);
    $newPlan = Plan::factory()->create(['key' => 'pro']);
    $newPlan->syncFeatures([Feature::MaterialTracking]);

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $shop->id,
        'feature' => Feature::MaterialTracking->value,
        'started_at' => now(),
    ])->save();

    $this->actingAs($admin)->patch(route('platform-admin.shops.plan', $shop), [
        'plan_id' => $newPlan->id,
    ])->assertRedirect()->assertSessionHas('status', function (string $status) {
        return str_contains($status, 'Cancelled 1 add-on subscription');
    });

    $subscription = ShopFeatureSubscription::withoutGlobalScopes()
        ->where('shop_id', $shop->id)
        ->where('feature', Feature::MaterialTracking->value)
        ->first();

    expect($subscription->cancelled_at)->not->toBeNull();
});

test('changing a shop\'s plan leaves an add-on subscription alone if the new plan does not bundle it', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create(['plan_id' => null]);
    $newPlan = Plan::factory()->create(['key' => 'start-up']);
    $newPlan->syncFeatures([Feature::Customers]);

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $shop->id,
        'feature' => Feature::MaterialTracking->value,
        'started_at' => now(),
    ])->save();

    $this->actingAs($admin)->patch(route('platform-admin.shops.plan', $shop), [
        'plan_id' => $newPlan->id,
    ])->assertRedirect();

    $subscription = ShopFeatureSubscription::withoutGlobalScopes()
        ->where('shop_id', $shop->id)
        ->where('feature', Feature::MaterialTracking->value)
        ->first();

    expect($subscription->cancelled_at)->toBeNull();
});

test('a non-platform-admin cannot suspend or change the plan of a shop', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $plan = Plan::factory()->create();

    $this->actingAs($owner)->post(route('platform-admin.shops.suspend', $shop))->assertForbidden();
    $this->actingAs($owner)->patch(route('platform-admin.shops.plan', $shop), [
        'plan_id' => $plan->id,
    ])->assertForbidden();
});

test('a platform admin can extend a shop\'s trial, stacking on top of what is left', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create(['trial_ends_at' => now()->addDays(3)]);

    $this->actingAs($admin)->patch(route('platform-admin.shops.trial.extend', $shop), [
        'days' => 7,
    ])->assertRedirect();

    // 3 days already there + 7 more = ~10 days out, not just 7 from today.
    expect($shop->fresh()->trial_ends_at->isBetween(now()->addDays(9), now()->addDays(11)))->toBeTrue();
});

test('extending a lapsed trial starts from today, not from the stale past end date', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDays(30)]);

    $this->actingAs($admin)->patch(route('platform-admin.shops.trial.extend', $shop), [
        'days' => 5,
    ])->assertRedirect();

    $shop->refresh();
    expect($shop->onTrial())->toBeTrue()
        ->and($shop->trial_ends_at->isBetween(now()->addDays(4), now()->addDays(6)))->toBeTrue();
});

test('extending a shop that never had a trial grants one starting today', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create(['trial_started_at' => null, 'trial_ends_at' => null]);

    $this->actingAs($admin)->patch(route('platform-admin.shops.trial.extend', $shop), [
        'days' => 10,
    ])->assertRedirect();

    $shop->refresh();
    expect($shop->onTrial())->toBeTrue()
        ->and($shop->trial_started_at)->not->toBeNull()
        ->and($shop->trial_baseline_days)->not->toBeNull();
});

test('a platform admin can end a shop\'s trial early', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create(['trial_ends_at' => now()->addDays(10), 'plan_id' => null]);

    $this->actingAs($admin)->post(route('platform-admin.shops.trial.expire', $shop))
        ->assertRedirect();

    expect($shop->fresh()->onTrial())->toBeFalse();
});

test('a non-platform-admin cannot extend or expire a shop\'s trial', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->patch(route('platform-admin.shops.trial.extend', $shop), ['days' => 5])
        ->assertForbidden();
    $this->actingAs($owner)->post(route('platform-admin.shops.trial.expire', $shop))
        ->assertForbidden();
});

test('a shop with no subscription_renews_at is neither paid nor overdue - it\'s simply not tracked', function () {
    $shop = Shop::factory()->create(['subscription_renews_at' => null]);

    expect($shop->isSubscriptionOverdue())->toBeFalse();
});

test('a platform admin can mark a shop paid, stacking on top of what is left', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create(['subscription_renews_at' => now()->addDays(3)]);

    $this->actingAs($admin)->patch(route('platform-admin.shops.subscription.extend', $shop), [
        'days' => 30,
    ])->assertRedirect();

    // 3 days already there + 30 more, not just 30 from today.
    expect($shop->fresh()->subscription_renews_at->isBetween(now()->addDays(32), now()->addDays(34)))->toBeTrue();
});

test('marking an overdue subscription paid starts from today, not the stale past date', function () {
    $admin = actingAsPlatformAdmin();
    $shop = Shop::factory()->create(['subscription_renews_at' => now()->subDays(10)]);

    $this->actingAs($admin)->patch(route('platform-admin.shops.subscription.extend', $shop), [
        'days' => 30,
    ])->assertRedirect();

    $shop->refresh();
    expect($shop->isSubscriptionOverdue())->toBeFalse()
        ->and($shop->subscription_renews_at->isBetween(now()->addDays(29), now()->addDays(31)))->toBeTrue();
});

test('a shop becomes overdue once its subscription_renews_at passes', function () {
    $shop = Shop::factory()->create(['subscription_renews_at' => now()->subMinute()]);

    expect($shop->isSubscriptionOverdue())->toBeTrue();
});

test('a non-platform-admin cannot mark a shop\'s subscription paid', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->patch(route('platform-admin.shops.subscription.extend', $shop), ['days' => 30])
        ->assertForbidden();
});

test('the shop directory can be filtered by subscription status', function () {
    $admin = actingAsPlatformAdmin();
    $paid = Shop::factory()->create(['name' => 'Paid Shop', 'subscription_renews_at' => now()->addDays(10)]);
    $overdue = Shop::factory()->create(['name' => 'Overdue Shop', 'subscription_renews_at' => now()->subDays(2)]);
    $notTracked = Shop::factory()->create(['name' => 'Untracked Shop', 'subscription_renews_at' => null]);

    $this->actingAs($admin)->get(route('platform-admin.index', ['subscription' => 'paid']))
        ->assertInertia(fn ($page) => $page->has('shops.data', 1)->where('shops.data.0.name', $paid->name));

    $this->actingAs($admin)->get(route('platform-admin.index', ['subscription' => 'overdue']))
        ->assertInertia(fn ($page) => $page->has('shops.data', 1)->where('shops.data.0.name', $overdue->name));

    $this->actingAs($admin)->get(route('platform-admin.index', ['subscription' => 'not_tracked']))
        ->assertInertia(fn ($page) => $page->has('shops.data', 1)->where('shops.data.0.name', $notTracked->name));
});

test('a platform admin can override a shop\'s seat limit, or clear it back to the plan default', function () {
    $admin = actingAsPlatformAdmin();
    $plan = Plan::factory()->create(['user_limit' => 5]);
    $shop = Shop::factory()->create(['plan_id' => $plan->id]);

    expect($shop->seatLimit())->toBe(5);

    $this->actingAs($admin)->patch(route('platform-admin.shops.seat-limit', $shop), [
        'seat_limit' => 20,
    ])->assertRedirect();

    expect($shop->fresh()->seatLimit())->toBe(20);

    $this->actingAs($admin)->patch(route('platform-admin.shops.seat-limit', $shop), [
        'seat_limit' => null,
    ])->assertRedirect();

    expect($shop->fresh()->seatLimit())->toBe(5);
});

test('a non-platform-admin cannot override a shop\'s seat limit', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->patch(route('platform-admin.shops.seat-limit', $shop), [
        'seat_limit' => 20,
    ])->assertForbidden();
});

test('potential revenue splits active-plan shops from trialing shops and excludes suspended ones', function () {
    $admin = actingAsPlatformAdmin();

    $startUp = Plan::factory()->create(['key' => 'start-up', 'monthly_price_cents' => 4900]);
    $pro = Plan::factory()->create(['key' => 'pro', 'monthly_price_cents' => 9900]);

    // Active, paying: counts toward active MRR.
    Shop::factory()->create(['plan_id' => $startUp->id, 'trial_ends_at' => now()->subDay()]);

    // Still on trial: counts toward trial-potential MRR, not active.
    Shop::factory()->create(['plan_id' => $pro->id, 'trial_ends_at' => now()->addDay()]);

    // Suspended: excluded from both, even though it's past trial on a paid plan.
    Shop::factory()->create([
        'plan_id' => $pro->id,
        'trial_ends_at' => now()->subDay(),
        'suspended_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('platform-admin.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('revenue.active_mrr_cents', 4900)
        ->where('revenue.trial_potential_mrr_cents', 9900)
    );
});
