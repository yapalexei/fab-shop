<?php

use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Shop;
use App\Models\Tool;
use App\Support\MaterialTypes;

test('every new shop starts with the built-in material types', function () {
    $shop = Shop::factory()->create();

    $names = MaterialType::withoutGlobalScopes()->where('shop_id', $shop->id)->orderBy('id')->pluck('name')->all();

    expect($names)->toBe(MaterialTypes::DEFAULTS)
        ->and(MaterialType::withoutGlobalScopes()->where('shop_id', $shop->id)->where('is_default', false)->count())->toBe(0);
});

test('seeding the built-ins again adds nothing', function () {
    $shop = Shop::factory()->create();

    MaterialTypes::seedDefaults($shop);

    expect(MaterialType::withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(count(MaterialTypes::DEFAULTS));
});

test('a shop can add, rename and delete its own material types', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('material-types.store'), ['name' => 'Chromoly (4130)'])->assertSessionHasNoErrors();
    $type = MaterialType::where('name', 'Chromoly (4130)')->sole();
    expect($type->is_default)->toBeFalse()->and($type->shop_id)->toBe($shop->id);

    $this->actingAs($user)->patch(route('material-types.update', $type), ['name' => 'Chromoly 4130'])->assertSessionHasNoErrors();
    expect($type->fresh()->name)->toBe('Chromoly 4130');

    $this->actingAs($user)->delete(route('material-types.destroy', $type))->assertSessionHas('status');
    expect(MaterialType::find($type->id))->toBeNull();
});

test('names are unique per shop regardless of case, but two shops can share one', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $otherUser = actingAsShopMember($otherShop);

    $this->actingAs($user)->post(route('material-types.store'), ['name' => 'aluminum'])->assertSessionHasErrors('name');
    $this->actingAs($user)->post(route('material-types.store'), ['name' => 'Inconel'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('material-types.store'), ['name' => 'INCONEL'])->assertSessionHasErrors('name');
    $this->actingAs($otherUser)->post(route('material-types.store'), ['name' => 'Inconel'])->assertSessionHasNoErrors();
});

test('a shop can add custom types only up to the limit', function () {
    config(['shop.max_custom_material_types' => 2]);
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('material-types.store'), ['name' => 'Chromoly'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('material-types.store'), ['name' => 'Inconel'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('material-types.store'), ['name' => 'Galvanized steel'])
        ->assertSessionHasErrors(['name' => 'This shop already has the maximum of 2 custom material types.']);

    expect(MaterialType::where('is_default', false)->count())->toBe(2);
});

test('built-in types cannot be renamed or deleted', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $aluminum = MaterialType::where('name', 'Aluminum')->sole();

    $this->actingAs($user)->patch(route('material-types.update', $aluminum), ['name' => 'Aluminium'])->assertForbidden();
    $this->actingAs($user)->delete(route('material-types.destroy', $aluminum))->assertSessionHas('error');

    expect($aluminum->fresh()->name)->toBe('Aluminum');
});

test('a type in use by a tool or material cannot be deleted', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $this->actingAs($user)->post(route('material-types.store'), ['name' => 'Chromoly']);
    $type = MaterialType::where('name', 'Chromoly')->sole();
    Tool::factory()->for($shop)->cutting('Chromoly')->create();

    $this->actingAs($user)->delete(route('material-types.destroy', $type))
        ->assertSessionHas('error', 'Chromoly is still used by 1 tool(s) and 0 material(s) - change those first.');

    expect($type->fresh())->not->toBeNull();
});

test('a user cannot see or change another shop\'s material types', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $foreign = MaterialType::withoutGlobalScopes()->where('shop_id', $otherShop->id)->first();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('material-types.index'))
        ->assertInertia(fn ($page) => $page->has('types', count(MaterialTypes::DEFAULTS))->where('customCount', 0)->where('customLimit', 20));
    $this->actingAs($user)->patch(route('material-types.update', $foreign), ['name' => 'Hijacked'])->assertNotFound();
    $this->actingAs($user)->delete(route('material-types.destroy', $foreign))->assertNotFound();
});

test('staff can see material types but not manage them by default', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($staff)->get(route('material-types.index'))->assertOk();
    $this->actingAs($staff)->post(route('material-types.store'), ['name' => 'Chromoly'])->assertForbidden();
});

test('a catalog material records what type it is', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $steel = MaterialType::where('name', 'Carbon steel')->sole();

    $this->actingAs($user)->post(route('materials.store'), [
        'name' => '1/4in Steel Plate', 'unit' => 'sheet', 'material_type_id' => $steel->id,
    ])->assertSessionHasNoErrors();

    expect(Material::sole()->materialType->name)->toBe('Carbon steel');
});

test('a material cannot use another shop\'s material type', function () {
    $shop = Shop::factory()->create();
    $foreign = MaterialType::withoutGlobalScopes()->where('shop_id', Shop::factory()->create()->id)->first();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('materials.store'), [
        'name' => 'x', 'material_type_id' => $foreign->id,
    ])->assertSessionHasErrors('material_type_id');
});

test('the quote builder receives each material\'s type for the tooling check', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $steel = MaterialType::where('name', 'Carbon steel')->sole();
    Material::factory()->for($shop)->create(['name' => '1/4in Steel Plate', 'material_type_id' => $steel->id]);

    $this->actingAs($user)->get(route('quotes.create'))
        ->assertInertia(fn ($page) => $page->where('materials.0.material_type', ['id' => $steel->id, 'name' => 'Carbon steel']));
});
