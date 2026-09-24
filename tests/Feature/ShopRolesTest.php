<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('only the owner passes the manage-shop gate', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->create(['owner_id' => $owner->id]);
    $owner->update(['current_shop_id' => $shop->id]);

    $staff = User::factory()->create(['current_shop_id' => $shop->id]);
    $shop->members()->attach($staff);

    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
    Role::findOrCreate('Owner');
    Role::findOrCreate('Staff');
    $owner->assignRole('Owner');
    $staff->assignRole('Staff');

    expect(Gate::forUser($owner)->allows('manage-shop'))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('manage-shop'))->toBeFalse();
});

test('a role assigned in one shop does not carry over to another shop', function () {
    $owner = User::factory()->create();

    $shopA = Shop::factory()->create(['owner_id' => $owner->id]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($shopA->id);
    Role::findOrCreate('Owner');
    $owner->assignRole('Owner');

    $shopB = Shop::factory()->create();
    $shopB->members()->attach($owner);
    $owner->update(['current_shop_id' => $shopB->id]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($shopB->id);

    expect($owner->hasRole('Owner'))->toBeFalse();
});
