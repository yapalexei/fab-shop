<?php

use App\Enums\BillingMethod;
use App\Models\Equipment;
use App\Models\Plan;
use App\Models\Shop;

test('equipment can be created with an hourly rate', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('equipment.store'), [
        'name' => 'Plasma cutter',
        'billing_method' => 'hourly',
        'rate' => 45,
    ])->assertRedirect(route('equipment.index'));

    $equipment = Equipment::sole();

    expect($equipment->shop_id)->toBe($shop->id)
        ->and($equipment->billing_method)->toBe(BillingMethod::Hourly)
        ->and((float) $equipment->rate)->toBe(45.0);
});

test('equipment can be created with a flat fee', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('equipment.store'), [
        'name' => 'Rented lift',
        'billing_method' => 'flat',
        'rate' => 200,
    ])->assertRedirect(route('equipment.index'));

    expect(Equipment::sole()->billing_method)->toBe(BillingMethod::Flat);
});

test('an invalid billing method is rejected', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('equipment.store'), [
        'name' => 'Plasma cutter',
        'billing_method' => 'subscription',
        'rate' => 45,
    ])->assertSessionHasErrors('billing_method');
});

test('a user cannot access another shop\'s equipment', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $foreignEquipment = Equipment::factory()->for($otherShop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('equipment.edit', $foreignEquipment))->assertNotFound();
});

test('the equipment route is read-only for a shop without the feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('equipment.index'))->assertOk();
    $this->actingAs($user)->post(route('equipment.store'), [])->assertForbidden();
});
