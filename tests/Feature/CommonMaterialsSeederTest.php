<?php

use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Shop;
use App\Support\MaterialTypes;
use Database\Seeders\CommonMaterialsSeeder;

test('the common materials seeder files every material under a built-in type for each shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    $this->seed(CommonMaterialsSeeder::class);

    foreach ([$shopA, $shopB] as $shop) {
        $materials = Material::query()->withoutGlobalScopes()->where('shop_id', $shop->id)->with('materialType')->get();

        expect($materials)->toHaveCount(count(CommonMaterialsSeeder::MATERIALS))
            ->and($materials->pluck('material_type_id')->contains(null))->toBeFalse();
        expect($materials->firstWhere('name', '6061-T6 aluminum')->materialType->name)->toBe('Aluminum')
            ->and($materials->firstWhere('name', '1018 cold-rolled steel')->materialType->name)->toBe('Carbon steel')
            ->and($materials->firstWhere('name', 'Baltic birch plywood')->materialType->name)->toBe('Wood');
    }
});

test('every seeded material names a real built-in type, and names are unique', function () {
    $types = collect(CommonMaterialsSeeder::MATERIALS)->pluck(0)->unique();
    $names = collect(CommonMaterialsSeeder::MATERIALS)->pluck(1)->map(fn ($n) => mb_strtolower($n));

    expect(MaterialTypes::DEFAULTS)->toContain(...$types->all())
        ->and($names->unique())->toHaveCount($names->count());
});

test('running the seeder again does not duplicate materials or overwrite edits', function () {
    $shop = Shop::factory()->create();
    $this->seed(CommonMaterialsSeeder::class);
    Material::query()->withoutGlobalScopes()->where('shop_id', $shop->id)->where('name', '304 stainless steel')->update(['notes' => 'edited']);

    $this->seed(CommonMaterialsSeeder::class);

    expect(Material::query()->withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(count(CommonMaterialsSeeder::MATERIALS))
        ->and(Material::query()->withoutGlobalScopes()->where('shop_id', $shop->id)->where('name', '304 stainless steel')->value('notes'))->toBe('edited');
});

test('DatabaseSeeder gives its shop the built-in material types despite WithoutModelEvents', function () {
    $this->seed();

    $shop = Shop::query()->first();

    expect(MaterialType::query()->withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBeGreaterThanOrEqual(count(MaterialTypes::DEFAULTS))
        ->and(Material::query()->withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(count(CommonMaterialsSeeder::MATERIALS));
});
