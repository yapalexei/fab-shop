<?php

use App\Enums\BusinessPartnerType;
use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Support\Facades\Storage;

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

test('a partner has a type, defaulting to supplier, and it can be changed', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($user)->post(route('business-partners.store'), ['name' => 'Metals R Us'])
        ->assertSessionHasNoErrors();
    expect(BusinessPartner::sole()->type)->toBe(BusinessPartnerType::Supplier);

    $partner = BusinessPartner::sole();
    $this->actingAs($user)->put(route('business-partners.update', $partner), [
        'name' => 'Metals R Us', 'type' => 'service_provider',
    ])->assertSessionHasNoErrors();
    expect($partner->fresh()->type)->toBe(BusinessPartnerType::ServiceProvider);

    $this->actingAs($user)->put(route('business-partners.update', $partner), [
        'name' => 'Metals R Us', 'type' => 'bogus',
    ])->assertSessionHasErrors('type');

    $this->actingAs($user)->get(route('business-partners.index'))
        ->assertInertia(fn ($page) => $page->where('businessPartners.0.type_label', 'Service provider'));
});

test('making a partner a customer links one business and copies contact details, not notes', function () {
    Storage::fake('public');
    $user = actingAsShopMember(Shop::factory()->create());
    $partner = BusinessPartner::factory()->create(['shop_id' => $user->current_shop_id, 'name' => 'Anodize Co', 'notes' => 'net 30, owes us a credit']);

    $this->actingAs($user)->post(route('business-partners.make-customer', $partner))
        ->assertRedirect();

    $customer = Customer::sole();
    expect($customer->name)->toBe('Anodize Co')
        ->and($customer->email)->toBe($partner->email)
        ->and($customer->notes)->toBeNull()
        ->and($customer->business_id)->not->toBeNull()
        ->and($customer->business_id)->toBe($partner->fresh()->business_id)
        ->and($partner->fresh()->customer->is($customer))->toBeTrue();

    // Doing it again doesn't create a second customer.
    $this->actingAs($user)->post(route('business-partners.make-customer', $partner));
    expect(Customer::count())->toBe(1);
});

test('shared details stay in sync from either side, but notes do not', function () {
    $user = actingAsShopMember(Shop::factory()->create());
    $partner = BusinessPartner::factory()->create(['shop_id' => $user->current_shop_id, 'name' => 'Anodize Co']);
    $this->actingAs($user)->post(route('business-partners.make-customer', $partner));
    $customer = Customer::sole();

    $this->actingAs($user)->put(route('business-partners.update', $partner), [
        'name' => 'Anodize Company', 'phone' => '555-0100', 'notes' => 'supplier note',
    ]);
    expect($customer->fresh()->name)->toBe('Anodize Company')
        ->and($customer->fresh()->phone)->toBe('555-0100')
        ->and($customer->fresh()->notes)->toBeNull();

    $this->actingAs($user)->put(route('customers.update', $customer), ['name' => 'Anodize & Sons']);
    expect($partner->fresh()->name)->toBe('Anodize & Sons')
        ->and($partner->fresh()->notes)->toBe('supplier note');
});

test('making a partner a customer links an existing customer of the same name instead of duplicating it', function () {
    $user = actingAsShopMember(Shop::factory()->create());
    $customer = Customer::factory()->create(['shop_id' => $user->current_shop_id, 'name' => 'acme fab', 'email' => 'keep@acme.test']);
    $partner = BusinessPartner::factory()->create(['shop_id' => $user->current_shop_id, 'name' => 'Acme Fab']);

    $this->actingAs($user)->post(route('business-partners.make-customer', $partner));

    expect(Customer::count())->toBe(1)
        ->and($customer->fresh()->email)->toBe('keep@acme.test')
        ->and($customer->fresh()->business_id)->toBe($partner->fresh()->business_id);
});

test('another shop\'s partner cannot be made a customer', function () {
    $user = actingAsShopMember(Shop::factory()->create());
    $foreign = BusinessPartner::factory()->create(['shop_id' => Shop::factory()]);

    $this->actingAs($user)->post(route('business-partners.make-customer', $foreign))->assertNotFound();
    expect(Customer::withoutGlobalScopes()->count())->toBe(0);
});
