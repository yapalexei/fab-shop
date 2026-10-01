<?php

use App\Models\Equipment;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Shop;
use App\Support\MaterialTypes;
use App\Support\SampleCatalog;
use Database\Seeders\CommonMaterialsSeeder;

function allNames(string $resource): array
{
    return collect(SampleCatalog::catalog($resource))->pluck('name')->all();
}

test('the dialog lists every sample item and flags the ones the shop already has', function () {
    $shop = Shop::factory()->create();
    Service::factory()->for($shop)->create(['name' => 'anodizing (type ii)']);

    $items = collect($this->actingAs(actingAsShopMember($shop))->getJson(route('sample-data.index', 'services'))->assertOk()->json('items'));

    expect($items)->toHaveCount(count(SampleCatalog::SERVICES))
        ->and($items->firstWhere('name', 'Anodizing (Type II)')['exists'])->toBeTrue()
        ->and($items->firstWhere('name', 'Powder coating')['exists'])->toBeFalse();
});

test('only the chosen sample items are added, and existing ones are left alone', function () {
    $shop = Shop::factory()->create();
    Service::factory()->for($shop)->create(['name' => 'anodizing (type ii)', 'notes' => 'mine']);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('sample-data.store', 'services'), ['items' => ['Powder coating', 'Anodizing (Type II)']])
        ->assertSessionHas('status', 'Added 1 sample services.');

    expect(Service::count())->toBe(2)
        ->and(Service::where('name', 'anodizing (type ii)')->value('notes'))->toBe('mine')
        ->and(Service::where('name', 'Powder coating')->exists())->toBeTrue();

    $this->actingAs($user)->post(route('sample-data.store', 'services'), ['items' => ['Powder coating']])
        ->assertSessionHas('status', 'You already have the services you picked.');
});

test('adding needs at least one item and rejects names that are not samples', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($user)->post(route('sample-data.store', 'services'), ['items' => []])->assertSessionHasErrors('items');
    $this->actingAs($user)->post(route('sample-data.store', 'services'), ['items' => ['Made up service']])->assertSessionHasErrors('items.0');

    expect(Service::count())->toBe(0);
});

test('every sample resource can be loaded in full', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    foreach (['materials', 'services', 'equipment'] as $resource) {
        $this->actingAs($user)->post(route('sample-data.store', $resource), ['items' => allNames($resource)])->assertSessionHasNoErrors();
    }

    $x1c = Equipment::where('name', '3D printer (Bambu Lab X1C)')->firstOrFail();

    expect(Material::count())->toBe(count(CommonMaterialsSeeder::MATERIALS))
        ->and(Service::count())->toBe(count(SampleCatalog::SERVICES))
        ->and(Equipment::count())->toBe(count(SampleCatalog::EQUIPMENT))
        ->and($x1c->billing_method->value)->toBe('hourly');
});

test('loading some materials also restores missing built-in material types', function () {
    $shop = Shop::factory()->create();
    MaterialType::query()->withoutGlobalScopes()->where('shop_id', $shop->id)->delete();

    $this->actingAs(actingAsShopMember($shop))->post(route('sample-data.store', 'materials'), ['items' => ['6061-T6 aluminum']])->assertSessionHasNoErrors();

    expect(Material::count())->toBe(1)
        ->and(Material::first()->materialType->name)->toBe('Aluminum')
        ->and(MaterialType::count())->toBe(count(MaterialTypes::DEFAULTS));
});

test('sample material types can be picked individually and never pass the custom limit', function () {
    config(['shop.max_custom_material_types' => 2]);
    $shop = Shop::factory()->create();

    expect(SampleCatalog::materialTypes($shop, ['Tool steel']))->toBe(1);

    SampleCatalog::materialTypes($shop, SampleCatalog::MATERIAL_TYPES);

    expect(MaterialType::query()->withoutGlobalScopes()->where('shop_id', $shop->id)->where('is_default', false)->count())->toBe(2);
});

test('the dialog needs the create permission for that resource', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopRole($shop, 'Staff');

    $this->actingAs($staff)->getJson(route('sample-data.index', 'services'))->assertForbidden();
    $this->actingAs($staff)->post(route('sample-data.store', 'services'), ['items' => ['Powder coating']])->assertForbidden();
    expect(Service::count())->toBe(0);
});

test('an unknown sample resource is a 404 and a locked quote builder is refused', function () {
    $shop = Shop::factory()->create();
    $this->actingAs(actingAsShopMember($shop))->post('/sample-data/customers', ['items' => ['x']])->assertNotFound();

    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $locked = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    $this->actingAs(actingAsShopMember($locked))->post(route('sample-data.store', 'services'), ['items' => ['Powder coating']])->assertForbidden();
});

test('sample data is added only to the current shop', function () {
    $shop = Shop::factory()->create();
    $other = Shop::factory()->create();

    $this->actingAs(actingAsShopMember($shop))->post(route('sample-data.store', 'services'), ['items' => allNames('services')]);

    expect(Service::query()->withoutGlobalScopes()->where('shop_id', $other->id)->count())->toBe(0);
});
