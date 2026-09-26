<?php

use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\PlatformSetting;
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

test('a platform admin can update a feature\'s price', function () {
    $admin = actingAsPlatformAdmin();
    FeatureSetting::ensureDefaults();

    $settings = collect(Feature::cases())->map(fn (Feature $feature) => [
        'feature' => $feature->value,
        'price_cents' => $feature === Feature::MaterialTracking ? 1500 : 2000,
    ])->all();

    $this->actingAs($admin)->patch(route('platform-admin.features.update'), [
        'default_trial_days' => 14,
        'settings' => $settings,
    ])->assertRedirect(route('platform-admin.features.index'));

    $row = FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->first();

    expect($row->price_cents)->toBe(1500);
});

test('updating feature pricing never touches trial_days - there is no admin control for it any more', function () {
    $admin = actingAsPlatformAdmin();
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->update(['trial_days' => 45]);

    $settings = collect(Feature::cases())->map(fn (Feature $feature) => [
        'feature' => $feature->value, 'price_cents' => 3000,
    ])->all();

    $this->actingAs($admin)->patch(route('platform-admin.features.update'), [
        'default_trial_days' => 14,
        'settings' => $settings,
    ])->assertRedirect(route('platform-admin.features.index'));

    expect(FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->value('trial_days'))->toBe(45);
});

test('feature pricing exposes the platform\'s default trial length', function () {
    $admin = actingAsPlatformAdmin();
    PlatformSetting::current()->update(['default_trial_days' => 21]);

    $this->actingAs($admin)->get(route('platform-admin.features.index'))
        ->assertInertia(fn ($page) => $page->where('defaultTrialDays', 21));
});

test('a platform admin can change the default trial length for new shops', function () {
    $admin = actingAsPlatformAdmin();
    FeatureSetting::ensureDefaults();

    $settings = collect(Feature::cases())->map(fn (Feature $feature) => [
        'feature' => $feature->value, 'price_cents' => 2000,
    ])->all();

    $this->actingAs($admin)->patch(route('platform-admin.features.update'), [
        'default_trial_days' => 21,
        'settings' => $settings,
    ])->assertRedirect(route('platform-admin.features.index'));

    expect(PlatformSetting::defaultTrialDays())->toBe(21);
});

test('ensureDefaults is idempotent and does not touch existing rows', function () {
    FeatureSetting::ensureDefaults();

    FeatureSetting::query()->where('feature', Feature::QuoteBuilder->value)->update(['price_cents' => 999]);

    FeatureSetting::ensureDefaults();

    expect(FeatureSetting::query()->count())->toBe(count(Feature::cases()));
    expect(FeatureSetting::query()->where('feature', Feature::QuoteBuilder->value)->value('price_cents'))->toBe(999);
});
