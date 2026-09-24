<?php

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;

test('a member of both shops can switch their current shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $user = User::factory()->create(['current_shop_id' => $shopA->id]);
    $shopA->members()->attach($user);
    $shopB->members()->attach($user);

    $this->actingAs($user)->post(route('current-shop.update', $shopB))->assertRedirect();

    expect($user->fresh()->current_shop_id)->toBe($shopB->id);
});

test('switching shops changes which tenant data is visible', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $user = User::factory()->create(['current_shop_id' => $shopA->id]);
    $shopA->members()->attach($user);
    $shopB->members()->attach($user);

    Customer::factory()->for($shopA)->create(['name' => 'Shop A Customer']);
    Customer::factory()->for($shopB)->create(['name' => 'Shop B Customer']);

    $this->actingAs($user)->get(route('customers.index'))
        ->assertInertia(fn ($page) => $page->where('customers.0.name', 'Shop A Customer')->has('customers', 1));

    $this->actingAs($user)->post(route('current-shop.update', $shopB));

    $this->actingAs($user)->get(route('customers.index'))
        ->assertInertia(fn ($page) => $page->where('customers.0.name', 'Shop B Customer')->has('customers', 1));
});

test('a user cannot switch to a shop they are not a member of', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $user = User::factory()->create(['current_shop_id' => $shopA->id]);
    $shopA->members()->attach($user);

    $this->actingAs($user)->post(route('current-shop.update', $shopB))->assertForbidden();

    expect($user->fresh()->current_shop_id)->toBe($shopA->id);
});
