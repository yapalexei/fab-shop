<?php

use App\Models\BusinessPartner;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Shop;

test('a material can be created and is scoped to the current shop', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('materials.store'), [
        'name' => '1/4in steel plate',
        'unit' => 'sheet',
    ])->assertRedirect(route('materials.index'));

    $material = Material::sole();

    expect($material->shop_id)->toBe($shop->id)
        ->and($material->name)->toBe('1/4in steel plate');
});

test('a user cannot access another shop\'s material', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $foreignMaterial = Material::factory()->for($otherShop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('materials.edit', $foreignMaterial))->assertNotFound();
});

test('a service can be created and is scoped to the current shop', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('services.store'), [
        'name' => 'Anodizing',
    ])->assertRedirect(route('services.index'));

    $service = Service::sole();

    expect($service->shop_id)->toBe($shop->id)
        ->and($service->name)->toBe('Anodizing');
});

test('a user cannot access another shop\'s service', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $foreignService = Service::factory()->for($otherShop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('services.edit', $foreignService))->assertNotFound();
});

test('a business partner can be created with material and service capabilities', function () {
    $shop = Shop::factory()->create();
    $material = Material::factory()->for($shop)->create();
    $service = Service::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('business-partners.store'), [
        'name' => 'Acme Supply Co',
        'materials' => [
            ['material_id' => $material->id, 'price' => 12.50],
        ],
        'services' => [
            ['service_id' => $service->id, 'price' => 75],
        ],
    ])->assertRedirect();

    $partner = BusinessPartner::sole();

    expect($partner->shop_id)->toBe($shop->id)
        ->and($partner->materials)->toHaveCount(1)
        ->and((float) $partner->materials->first()->pivot->price)->toBe(12.5)
        ->and($partner->services)->toHaveCount(1)
        ->and((float) $partner->services->first()->pivot->price)->toBe(75.0);
});

test('a business partner cannot offer a material belonging to another shop', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $foreignMaterial = Material::factory()->for($otherShop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('business-partners.store'), [
        'name' => 'Acme Supply Co',
        'materials' => [
            ['material_id' => $foreignMaterial->id, 'price' => 10],
        ],
    ])->assertSessionHasErrors(['materials.0.material_id']);
});

test('updating a business partner replaces its capabilities', function () {
    $shop = Shop::factory()->create();
    $materialA = Material::factory()->for($shop)->create();
    $materialB = Material::factory()->for($shop)->create();
    $partner = BusinessPartner::factory()->for($shop)->create();
    $partner->materials()->attach($materialA->id, ['price' => 5]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->put(route('business-partners.update', $partner), [
        'name' => $partner->name,
        'materials' => [
            ['material_id' => $materialB->id, 'price' => 20],
        ],
    ])->assertRedirect();

    $partner->refresh();

    expect($partner->materials)->toHaveCount(1)
        ->and($partner->materials->first()->id)->toBe($materialB->id)
        ->and((float) $partner->materials->first()->pivot->price)->toBe(20.0);
});

test('a user cannot access another shop\'s business partner', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $foreignPartner = BusinessPartner::factory()->for($otherShop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('business-partners.edit', $foreignPartner))->assertNotFound();
});

test('material, service, and business partner routes are read-only for a shop without the feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('materials.index'))->assertOk();
    $this->actingAs($user)->post(route('materials.store'), [])->assertForbidden();
    $this->actingAs($user)->get(route('services.index'))->assertOk();
    $this->actingAs($user)->get(route('business-partners.index'))->assertOk();
    $this->actingAs($user)->post(route('materials.store'), [])->assertForbidden();
});
