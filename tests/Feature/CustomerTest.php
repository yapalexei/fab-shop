<?php

use App\Models\Customer;
use App\Models\Shop;

test('a user only sees customers belonging to their current shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    Customer::factory()->for($shopA)->create(['name' => 'Shop A Customer']);
    Customer::factory()->for($shopB)->create(['name' => 'Shop B Customer']);

    $user = actingAsShopMember($shopA);

    $response = $this->actingAs($user)->get(route('customers.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Customers/Index')
        ->has('customers', 1)
        ->where('customers.0.name', 'Shop A Customer')
    );
});

test('a user cannot read another shop\'s customer via direct id access', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $foreignCustomer = Customer::factory()->for($shopB)->create();

    $user = actingAsShopMember($shopA);

    $this->actingAs($user)->get(route('customers.edit', $foreignCustomer))->assertNotFound();
});

test('a user cannot update another shop\'s customer via direct id access', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $foreignCustomer = Customer::factory()->for($shopB)->create(['name' => 'Original']);

    $user = actingAsShopMember($shopA);

    $this->actingAs($user)->put(route('customers.update', $foreignCustomer), [
        'name' => 'Hijacked',
    ])->assertNotFound();

    expect($foreignCustomer->fresh()->name)->toBe('Original');
});

test('a customer can be created and is scoped to the creator\'s current shop', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('customers.store'), [
        'name' => 'Acme Fabrication',
        'email' => 'billing@acme.test',
        'phone' => '555-0100',
        'address' => '123 Main St',
        'notes' => 'Prefers email contact',
    ])->assertRedirect(route('customers.index'));

    $customer = Customer::sole();

    expect($customer->shop_id)->toBe($shop->id)
        ->and($customer->name)->toBe('Acme Fabrication');
});

test('a customer can be updated', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $customer = Customer::factory()->for($shop)->create(['name' => 'Old Name']);

    $this->actingAs($user)->put(route('customers.update', $customer), [
        'name' => 'New Name',
        'email' => null,
        'phone' => null,
        'address' => null,
        'notes' => null,
    ])->assertRedirect(route('customers.index'));

    expect($customer->fresh()->name)->toBe('New Name');
});

test('a customer can be deleted', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $customer = Customer::factory()->for($shop)->create();

    $this->actingAs($user)->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));

    expect(Customer::find($customer->id))->toBeNull();
});

test('name is required to create a customer', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('customers.store'), [
        'name' => '',
    ])->assertSessionHasErrors('name');
});
