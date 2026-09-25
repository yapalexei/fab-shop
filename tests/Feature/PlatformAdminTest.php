<?php

use App\Models\Plan;
use App\Models\Shop;

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
        ->has('shops', 2)
        ->where('shops.0.name', $shopB->name)
        ->where('shops.1.name', $shopA->name)
    );
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

test('a non-platform-admin cannot suspend or change the plan of a shop', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $plan = Plan::factory()->create();

    $this->actingAs($owner)->post(route('platform-admin.shops.suspend', $shop))->assertForbidden();
    $this->actingAs($owner)->patch(route('platform-admin.shops.plan', $shop), [
        'plan_id' => $plan->id,
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
