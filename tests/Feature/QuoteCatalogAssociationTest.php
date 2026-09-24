<?php

use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;

test('a material line item can reference a material and the partner supplying it', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $material = Material::factory()->for($shop)->create();
    $partner = BusinessPartner::factory()->for($shop)->create();
    $partner->materials()->attach($material->id, ['price' => 12]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'line_items' => [[
            'type' => 'material', 'description' => $material->name,
            'quantity' => 2, 'unit_cost' => 12, 'markup_percent' => 20,
            'material_id' => $material->id, 'business_partner_id' => $partner->id,
        ]],
    ])->assertRedirect();

    $item = Quote::sole()->lineItems->first();

    expect($item->material_id)->toBe($material->id)
        ->and($item->business_partner_id)->toBe($partner->id);
});

test('a labor line item can reference an internal employee', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);
    $employee = User::factory()->create(['current_shop_id' => $shop->id]);
    $shop->members()->attach($employee, ['hourly_rate' => 40]);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'line_items' => [[
            'type' => 'labor', 'description' => 'Welding',
            'quantity' => 3, 'unit_cost' => 40, 'markup_percent' => 25,
            'user_id' => $employee->id,
        ]],
    ])->assertRedirect();

    expect(Quote::sole()->lineItems->first()->user_id)->toBe($employee->id);
});

test('a labor line item can reference an outsourced service and partner', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $service = Service::factory()->for($shop)->create();
    $partner = BusinessPartner::factory()->for($shop)->create();
    $partner->services()->attach($service->id, ['price' => 75]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'line_items' => [[
            'type' => 'labor', 'description' => $service->name,
            'quantity' => 1, 'unit_cost' => 75, 'markup_percent' => 0,
            'service_id' => $service->id, 'business_partner_id' => $partner->id,
        ]],
    ])->assertRedirect();

    $item = Quote::sole()->lineItems->first();

    expect($item->service_id)->toBe($service->id)
        ->and($item->business_partner_id)->toBe($partner->id);
});

test('a labor line item cannot have both an employee and an outsourced service', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $service = Service::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);
    $employee = User::factory()->create(['current_shop_id' => $shop->id]);
    $shop->members()->attach($employee, ['hourly_rate' => 40]);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'line_items' => [[
            'type' => 'labor', 'description' => 'Confused line',
            'quantity' => 1, 'unit_cost' => 40, 'markup_percent' => 0,
            'user_id' => $employee->id, 'service_id' => $service->id,
        ]],
    ])->assertSessionHasErrors(['line_items.0.user_id']);
});

test('an equipment line item can reference an equipment record', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $equipment = Equipment::factory()->for($shop)->create(['billing_method' => 'hourly', 'rate' => 50]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'line_items' => [[
            'type' => 'equipment', 'description' => $equipment->name,
            'quantity' => 2, 'unit_cost' => 50, 'markup_percent' => 0,
            'equipment_id' => $equipment->id,
        ]],
    ])->assertRedirect();

    expect(Quote::sole()->lineItems->first()->equipment_id)->toBe($equipment->id);
});

test('a quote line item cannot reference a material belonging to another shop', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $foreignMaterial = Material::factory()->for($otherShop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'line_items' => [[
            'type' => 'material', 'description' => 'X',
            'quantity' => 1, 'unit_cost' => 1, 'markup_percent' => 0,
            'material_id' => $foreignMaterial->id,
        ]],
    ])->assertSessionHasErrors(['line_items.0.material_id']);
});

test('a quote line item cannot reference an employee outside the current shop', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);
    $outsider = actingAsShopMember($otherShop);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'line_items' => [[
            'type' => 'labor', 'description' => 'X',
            'quantity' => 1, 'unit_cost' => 1, 'markup_percent' => 0,
            'user_id' => $outsider->id,
        ]],
    ])->assertSessionHasErrors(['line_items.0.user_id']);
});
