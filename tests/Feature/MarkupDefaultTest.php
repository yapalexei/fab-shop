<?php

use App\Enums\LineItemType;
use App\Models\MarkupDefault;
use App\Models\Plan;
use App\Models\Shop;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('a type with no default returns 0', function () {
    Shop::factory()->create();

    expect(MarkupDefault::forType(LineItemType::Labor))->toBe(0.0);
});

test('the owner can set markup defaults for all three types', function () {
    $owner = Shop::factory()->create()->owner;
    $shop = $owner->ownedShops->first();
    $owner->forceFill(['current_shop_id' => $shop->id])->save();

    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
    Role::findOrCreate('Owner');
    $owner->assignRole('Owner');

    $this->actingAs($owner)->patch(route('markup-defaults.update'), [
        'defaults' => [
            ['line_item_type' => 'material', 'default_markup_percent' => 20],
            ['line_item_type' => 'labor', 'default_markup_percent' => 35],
            ['line_item_type' => 'equipment', 'default_markup_percent' => 10],
        ],
    ])->assertRedirect(route('markup-defaults.index'));

    expect(MarkupDefault::forType(LineItemType::Material))->toBe(20.0)
        ->and(MarkupDefault::forType(LineItemType::Labor))->toBe(35.0)
        ->and(MarkupDefault::forType(LineItemType::Equipment))->toBe(10.0);
});

test('a non-owner cannot set markup defaults', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('markup-defaults.update'), [
        'defaults' => [
            ['line_item_type' => 'material', 'default_markup_percent' => 20],
        ],
    ])->assertForbidden();
});

test('markup defaults are scoped per shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    (new MarkupDefault)->forceFill([
        'shop_id' => $shopA->id,
        'line_item_type' => 'material',
        'default_markup_percent' => 50,
    ])->save();

    $userA = actingAsShopMember($shopA);
    $userB = actingAsShopMember($shopB);

    $this->actingAs($userA);
    expect(MarkupDefault::forType(LineItemType::Material))->toBe(50.0);

    $this->actingAs($userB);
    expect(MarkupDefault::forType(LineItemType::Material))->toBe(0.0);
});

test('the markup-defaults route is locked for a shop without the feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('markup-defaults.index'))->assertForbidden();
});
