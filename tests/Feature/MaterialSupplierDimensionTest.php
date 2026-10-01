<?php

use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Quote;
use App\Models\Shop;

function partnerPayload(array $materials): array
{
    return ['name' => 'Metal Supply Co', 'materials' => $materials];
}

test('a supplier can price the same material at several sizes', function () {
    $shop = Shop::factory()->create();
    $material = Material::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('business-partners.store'), partnerPayload([
        ['material_id' => $material->id, 'dimension' => '4 ft', 'price' => 48],
        ['material_id' => $material->id, 'dimension' => '4 in', 'price' => 4],
        ['material_id' => $material->id, 'dimension' => '', 'price' => 9],
    ]))->assertSessionHasNoErrors();

    $rows = BusinessPartner::sole()->materials()->get()->map(fn ($m) => [$m->pivot->dimension, (float) $m->pivot->price])->all();

    expect($rows)->toEqualCanonicalizing([['4 ft', 48.0], ['4 in', 4.0], [null, 9.0]]);
});

test('listing a material twice at the same size is refused, ignoring case and spacing', function () {
    $shop = Shop::factory()->create();
    $material = Material::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('business-partners.store'), partnerPayload([
        ['material_id' => $material->id, 'dimension' => '4 FT', 'price' => 48],
        ['material_id' => $material->id, 'dimension' => ' 4 ft ', 'price' => 50],
    ]))->assertSessionHasErrors('materials.1.dimension');

    $this->actingAs($user)->post(route('business-partners.store'), partnerPayload([
        ['material_id' => $material->id, 'price' => 1],
        ['material_id' => $material->id, 'dimension' => '', 'price' => 2],
    ]))->assertSessionHasErrors('materials.1.dimension');

    expect(BusinessPartner::count())->toBe(0);
});

test('editing a supplier replaces its sized prices', function () {
    $shop = Shop::factory()->create();
    $material = Material::factory()->for($shop)->create();
    $partner = BusinessPartner::factory()->for($shop)->create();
    $partner->materials()->attach($material->id, ['price' => 5, 'dimension' => '1 ft']);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->put(route('business-partners.update', $partner), partnerPayload([
        ['material_id' => $material->id, 'dimension' => '2 ft', 'price' => 10],
    ]))->assertSessionHasNoErrors();

    $rows = $partner->fresh()->materials;

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->pivot->dimension)->toBe('2 ft');

    $this->actingAs($user)->get(route('business-partners.edit', $partner))->assertInertia(fn ($page) => $page
        ->where('businessPartner.materials.0.dimension', '2 ft'));
});

test('the quote builder gets each size as its own supplier price and a line remembers the one chosen', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $material = Material::factory()->for($shop)->create();
    $partner = BusinessPartner::factory()->for($shop)->create();
    $partner->materials()->attach($material->id, ['price' => 48, 'dimension' => '4 ft']);
    $partner->materials()->attach($material->id, ['price' => 4, 'dimension' => '4 in']);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('quotes.create'))->assertInertia(fn ($page) => $page
        ->where('materials.0.partners', fn ($partners) => collect($partners)->pluck('dimension')->sort()->values()->all() === ['4 ft', '4 in']));

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'line_items' => [[
            'type' => 'material', 'description' => $material->name,
            'quantity' => 1, 'unit_cost' => 4, 'markup_percent' => 0,
            'material_id' => $material->id, 'business_partner_id' => $partner->id, 'material_dimension' => '4 in',
        ]],
    ])->assertSessionHasNoErrors();

    expect(Quote::sole()->lineItems->first()->material_dimension)->toBe('4 in');
});

test('a sized price is not used as the job material suggested cost', function () {
    $shop = Shop::factory()->create();
    $material = Material::factory()->for($shop)->create();
    $partner = BusinessPartner::factory()->for($shop)->create();
    $partner->materials()->attach($material->id, ['price' => 4, 'dimension' => '4 in']);

    $suggested = fn () => Material::query()->withoutGlobalScopes()->with('businessPartners')->find($material->id)
        ->businessPartners->whereNull('pivot.dimension')->min('pivot.price');

    expect($suggested())->toBeNull();

    $partner->materials()->attach($material->id, ['price' => 9]);

    expect((float) $suggested())->toBe(9.0);
});
