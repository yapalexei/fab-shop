<?php

use App\Enums\ShopPermission;
use App\Models\Shop;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function grantToRole(Shop $shop, string $role, ShopPermission $permission): void
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
    Role::findOrCreate($role)->givePermissionTo($permission->value);
}

test('the owner always has every permission and admin/staff start with none', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopRole($shop, 'Owner');
    $admin = actingAsShopRole($shop, 'Admin');
    $staff = actingAsShopRole($shop, 'Staff');

    foreach (ShopPermission::cases() as $permission) {
        expect($owner->hasShopPermission($permission))->toBeTrue()
            ->and($admin->hasShopPermission($permission))->toBeFalse()
            ->and($staff->hasShopPermission($permission))->toBeFalse();
    }
});

test('the owner can grant a permission to a role', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopRole($shop, 'Owner');
    $admin = actingAsShopRole($shop, 'Admin');

    $this->actingAs($owner)->put(route('shop.permissions.update'), [
        'grants' => ['Admin' => ['view-financials'], 'Staff' => []],
    ])->assertRedirect();

    expect($admin->fresh()->hasShopPermission(ShopPermission::ViewFinancials))->toBeTrue();
});

test('role permissions set in one shop do not leak into another shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $user = actingAsShopRole($shopA, 'Admin');
    $shopB->members()->attach($user);
    app(PermissionRegistrar::class)->setPermissionsTeamId($shopB->id);
    $user->assignRole(Role::findOrCreate('Admin'));

    grantToRole($shopA, 'Admin', ShopPermission::ViewFinancials);

    app(PermissionRegistrar::class)->setPermissionsTeamId($shopA->id);
    expect($user->fresh()->hasShopPermission(ShopPermission::ViewFinancials))->toBeTrue();

    $user->current_shop_id = $shopB->id;
    $user->save();
    app(PermissionRegistrar::class)->setPermissionsTeamId($shopB->id);
    expect($user->fresh()->hasShopPermission(ShopPermission::ViewFinancials))->toBeFalse();
});

test('a member override can allow what the role does not have', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopRole($shop, 'Owner');
    $staff = actingAsShopRole($shop, 'Staff');

    $this->actingAs($owner)->put(route('team.access.update', $staff), [
        'role' => 'Staff',
        'overrides' => ['view-financials' => true, 'manage-shop-settings' => null],
    ])->assertRedirect(route('team.access.edit', $staff));

    $fresh = $staff->fresh();
    expect($fresh->hasShopPermission(ShopPermission::ViewFinancials))->toBeTrue()
        ->and($fresh->hasShopPermission(ShopPermission::ManageShopSettings))->toBeFalse()
        ->and($shop->members()->find($staff->id)->pivot->permission_overrides)->toBe(['view-financials' => true]);
});

test('a member override can deny what the role grants', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopRole($shop, 'Owner');
    $admin = actingAsShopRole($shop, 'Admin');
    grantToRole($shop, 'Admin', ShopPermission::ViewFinancials);

    $this->actingAs($owner)->put(route('team.access.update', $admin), [
        'role' => 'Admin',
        'overrides' => ['view-financials' => false],
    ]);

    expect($admin->fresh()->hasShopPermission(ShopPermission::ViewFinancials))->toBeFalse();
});

test('a member override in one shop does not apply in another shop they belong to', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = actingAsShopRole($shopA, 'Owner');
    $user = actingAsShopRole($shopA, 'Staff');
    $shopB->members()->attach($user);

    $this->actingAs($ownerA)->put(route('team.access.update', $user), [
        'role' => 'Staff',
        'overrides' => ['view-financials' => true],
    ]);

    $user->current_shop_id = $shopB->id;
    $user->save();
    app(PermissionRegistrar::class)->setPermissionsTeamId($shopB->id);

    expect($user->fresh()->hasShopPermission(ShopPermission::ViewFinancials))->toBeFalse();
});

test('the owner can change a member\'s role', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopRole($shop, 'Owner');
    $staff = actingAsShopRole($shop, 'Staff');

    $this->actingAs($owner)->put(route('team.access.update', $staff), [
        'role' => 'Admin',
        'overrides' => [],
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
    expect($staff->fresh()->getRoleNames()->all())->toBe(['Admin']);
});

test('nobody can be made a second owner, and the owner\'s own access cannot be edited', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopRole($shop, 'Owner');
    $staff = actingAsShopRole($shop, 'Staff');

    $this->actingAs($owner)->put(route('team.access.update', $staff), ['role' => 'Owner', 'overrides' => []])
        ->assertSessionHasErrors('role');

    $this->actingAs($owner)->get(route('team.access.edit', $owner))->assertForbidden();
});

test('only the owner can manage permissions - not even an admin trusted with shop settings', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopRole($shop, 'Admin');
    $staff = actingAsShopRole($shop, 'Staff');
    grantToRole($shop, 'Admin', ShopPermission::ManageShopSettings);

    $this->actingAs($admin)->get(route('shop.permissions.edit'))->assertForbidden();
    $this->actingAs($admin)->put(route('shop.permissions.update'), ['grants' => ['Admin' => ['view-financials']]])->assertForbidden();
    $this->actingAs($admin)->put(route('team.access.update', $staff), ['role' => 'Admin', 'overrides' => []])->assertForbidden();
});

test('the owner cannot edit access for someone outside their current shop', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopRole($shop, 'Owner');
    $outsider = actingAsShopRole(Shop::factory()->create(), 'Staff');

    $this->actingAs($owner)->get(route('team.access.edit', $outsider))->assertNotFound();
    $this->actingAs($owner)->put(route('team.access.update', $outsider), ['role' => 'Admin', 'overrides' => []])->assertNotFound();
});

test('unknown permission names are rejected', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopRole($shop, 'Owner');
    $staff = actingAsShopRole($shop, 'Staff');

    $this->actingAs($owner)->put(route('shop.permissions.update'), ['grants' => ['Staff' => ['delete-everything']]])
        ->assertSessionHasErrors('grants.Staff.0');
    $this->actingAs($owner)->put(route('team.access.update', $staff), ['role' => 'Staff', 'overrides' => ['delete-everything' => true]])
        ->assertSessionHasErrors('overrides');
});

test('permission flags are shared with every page', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopRole($shop, 'Staff');

    $this->actingAs($staff)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.can', ['manageShop' => false, 'viewFinancials' => false, 'manageShopSettings' => false]));
});
