<?php

use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('the owner can set a team member\'s hourly rate', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->create(['owner_id' => $owner->id]);
    $owner->forceFill(['current_shop_id' => $shop->id])->save();

    $staff = User::factory()->create(['current_shop_id' => $shop->id]);
    $shop->members()->attach($staff);

    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
    Role::findOrCreate('Owner');
    $owner->assignRole('Owner');

    $this->actingAs($owner)->patch(route('team.update', $staff), [
        'hourly_rate' => 32.5,
    ])->assertRedirect(route('team.index'));

    expect((float) $shop->members()->find($staff->id)->pivot->hourly_rate)->toBe(32.5);
});

test('a non-owner cannot set a team member\'s hourly rate', function () {
    $shop = Shop::factory()->create();
    $staffA = actingAsShopMember($shop);
    $staffB = actingAsShopMember($shop);

    $this->actingAs($staffA)->patch(route('team.update', $staffB), [
        'hourly_rate' => 50,
    ])->assertForbidden();
});

test('a user cannot set the hourly rate of someone outside their current shop', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $owner = $shop->owner;
    $owner->forceFill(['current_shop_id' => $shop->id])->save();

    $outsider = actingAsShopMember($otherShop);

    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
    Role::findOrCreate('Owner');
    $owner->assignRole('Owner');

    $this->actingAs($owner)->patch(route('team.update', $outsider), [
        'hourly_rate' => 50,
    ])->assertNotFound();
});

test('the team index lists members with their role and rate', function () {
    $shop = Shop::factory()->create();
    $owner = $shop->owner;
    $owner->forceFill(['current_shop_id' => $shop->id])->save();
    $shop->members()->updateExistingPivot($owner->id, ['hourly_rate' => 28]);

    $response = $this->actingAs($owner)->get(route('team.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Team/Index')
        ->has('members', 1)
        ->where('members.0.hourly_rate', 28)
    );
});
