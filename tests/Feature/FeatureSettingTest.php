<?php

use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Shop;

test('a non-platform-admin cannot reach feature pricing', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->get(route('platform-admin.features.index'))->assertForbidden();
});

test('feature pricing seeds a row for every feature on first view', function () {
    $admin = actingAsPlatformAdmin();

    expect(FeatureSetting::query()->count())->toBe(0);

    $response = $this->actingAs($admin)->get(route('platform-admin.features.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('PlatformAdmin/Features')
        ->has('settings', count(Feature::cases()))
    );

    expect(FeatureSetting::query()->count())->toBe(count(Feature::cases()));
});

test('a platform admin can update a feature price and trial length', function () {
    $admin = actingAsPlatformAdmin();
    FeatureSetting::ensureDefaults();

    $settings = collect(Feature::cases())->map(fn (Feature $feature) => [
        'feature' => $feature->value,
        'price_cents' => $feature === Feature::MaterialTracking ? 1500 : 2000,
        'trial_days' => $feature === Feature::MaterialTracking ? 30 : 14,
    ])->all();

    $this->actingAs($admin)->patch(route('platform-admin.features.update'), [
        'settings' => $settings,
    ])->assertRedirect(route('platform-admin.features.index'));

    $row = FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->first();

    expect($row->price_cents)->toBe(1500)
        ->and($row->trial_days)->toBe(30);
});

test('ensureDefaults is idempotent and does not touch existing rows', function () {
    FeatureSetting::ensureDefaults();

    FeatureSetting::query()->where('feature', Feature::QuoteBuilder->value)->update(['price_cents' => 999]);

    FeatureSetting::ensureDefaults();

    expect(FeatureSetting::query()->count())->toBe(count(Feature::cases()));
    expect(FeatureSetting::query()->where('feature', Feature::QuoteBuilder->value)->value('price_cents'))->toBe(999);
});
