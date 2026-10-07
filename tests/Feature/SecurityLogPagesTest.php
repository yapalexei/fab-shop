<?php

use App\Models\Shop;
use App\Support\SecurityLog;

function logAs($user, string $what, array $props = []): void
{
    SecurityLog::record($what, $user, $props);
}

test('the platform admin sees the whole security log and can filter it', function () {
    $a = actingAsShopMember(Shop::factory()->create());
    $b = actingAsShopMember(Shop::factory()->create());
    $admin = actingAsPlatformAdmin();
    logAs($a, 'login');
    logAs($b, 'password changed');
    SecurityLog::record('login failed', null, ['email' => 'guesser@example.com']);

    $this->actingAs($admin)->get(route('platform-admin.security.log'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('PlatformAdmin/SecurityLog')->where('log.total', 3));

    $this->actingAs($admin)->get(route('platform-admin.security.log', ['event' => 'login failed']))
        ->assertInertia(fn ($page) => $page->where('log.total', 1));

    $this->actingAs($admin)->get(route('platform-admin.security.log', ['q' => 'guesser']))
        ->assertInertia(fn ($page) => $page->where('log.total', 1));
});

test('a shop owner sees only their own shop in the log', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = actingAsShopMember($shopA);
    $ownerB = actingAsShopMember($shopB);
    logAs($ownerA, 'login');
    logAs($ownerB, 'password changed');
    SecurityLog::record('login failed', null, ['email' => $ownerA->email]);

    $this->actingAs($ownerA)->get(route('shop.security.log'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('log.total', 1)
            ->where('log.data.0.event', 'login'));
});

test("what platform staff did to a shop shows in that shop's log, but not what they did to other shops", function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);
    $admin = actingAsPlatformAdmin();
    $admin->current_shop_id = $shop->id; // staff who also happen to sit in this shop
    $admin->save();

    logAs($admin, 'platform admin: shop suspended', ['target_shop_id' => $shop->id]);
    logAs($admin, 'platform admin: plan changed', ['target_shop_id' => Shop::factory()->create()->id]);

    $this->actingAs($owner)->get(route('shop.security.log'))
        ->assertInertia(fn ($page) => $page
            ->where('log.total', 1)
            ->where('log.data.0.event', 'platform admin: shop suspended'));
});

test('only the owner can open the shop security log', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');

    $this->actingAs($admin)->get(route('shop.security.log'))->assertForbidden();
});

test('a platform admin action is written to the log', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsPlatformAdmin();

    $this->actingAs($admin)->post(route('platform-admin.shops.suspend', $shop))->assertRedirect();

    $this->actingAs($admin)->get(route('platform-admin.security.log', ['event' => 'platform admin: shop suspended']))
        ->assertInertia(fn ($page) => $page->where('log.total', 1)->where('log.data.0.details.target_shop_id', (string) $shop->id));
});
