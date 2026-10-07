<?php

use App\Models\Shop;

test('the shop Security page is an index of its sub-pages', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->get(route('shop.security.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ShopSettings/Security'));

    $this->actingAs($owner)->get(route('shop.security.log'))
        ->assertInertia(fn ($page) => $page->component('ShopSettings/SecurityLog'));

    $this->actingAs($owner)->get(route('shop.permissions.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Permissions/Edit'));

    $this->actingAs($owner)->get(route('shop.security.policy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ShopSettings/SecurityTwoFactor')
            ->where('twoFactor.follows_default', true)
            ->has('twoFactor.role_options'));
});

test('the two-factor requirement is no longer on the shop settings page', function () {
    $owner = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($owner)->get(route('shop.settings.edit'))
        ->assertInertia(fn ($page) => $page->missing('ownerControls.twoFactor'));
});

test('roles and permissions now live under the security path', function () {
    expect(route('shop.permissions.edit', absolute: false))->toBe('/shop/security/permissions')
        ->and(route('shop.security.log', absolute: false))->toBe('/shop/security/log');
});

test('the index and every sub-page stay owner-only', function () {
    $admin = actingAsShopMember(Shop::factory()->create(), 'Admin');

    foreach (['shop.security.index', 'shop.security.log', 'shop.security.policy', 'shop.permissions.edit'] as $name) {
        $this->actingAs($admin)->get(route($name))->assertForbidden();
    }
});

test('the platform Security page is an index of the two-factor default and the log', function () {
    $admin = actingAsPlatformAdmin();

    $this->actingAs($admin)->get(route('platform-admin.security.index'))
        ->assertInertia(fn ($page) => $page->component('PlatformAdmin/Security'));
    $this->actingAs($admin)->get(route('platform-admin.security.policy'))
        ->assertInertia(fn ($page) => $page->component('PlatformAdmin/SecurityPolicy')->has('policy')->has('roleOptions'));
    $this->actingAs($admin)->get(route('platform-admin.security.log'))
        ->assertInertia(fn ($page) => $page->component('PlatformAdmin/SecurityLog')->has('log'));
});

test('the platform Security sub-pages are for platform admins only', function () {
    $owner = actingAsShopMember(Shop::factory()->create());

    foreach (['index', 'policy', 'log'] as $page) {
        $this->actingAs($owner)->get(route("platform-admin.security.{$page}"))->assertForbidden();
    }
});
