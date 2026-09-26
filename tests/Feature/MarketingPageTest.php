<?php

use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Shop;
use Database\Seeders\PlanSeeder;

test('the marketing page is publicly accessible', function () {
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Welcome'));
});

test('an already-authenticated visitor can still view the marketing page', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get('/')->assertOk();
});

test('tier contents on the marketing page are read live from the seeded plans', function () {
    $this->seed(PlanSeeder::class);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->has('tiers', 4)
        ->where('tiers.0.key', 'free')
        ->where('tiers.0.isTrialOnly', true)
        ->where('tiers.1.key', 'solo')
        ->where('tiers.2.key', 'start-up')
        ->where('tiers.3.key', 'pro')
        // Solo includes quoting but not the job board; Start-up includes the
        // job board but not QuickBooks sync; Pro includes both - matches
        // PlanSeeder exactly, so this test breaks the moment the two drift.
        ->where('tiers.1.features', fn ($features) => collect($features)->firstWhere('key', 'quote-builder')['included'] === true
            && collect($features)->firstWhere('key', 'job-board')['included'] === false)
        ->where('tiers.2.features', fn ($features) => collect($features)->firstWhere('key', 'job-board')['included'] === true
            && collect($features)->firstWhere('key', 'quickbooks-sync')['included'] === false)
        ->where('tiers.3.features', fn ($features) => collect($features)->firstWhere('key', 'quickbooks-sync')['included'] === true));
});

test('unshipped features are left off the marketing page entirely, not teased as coming soon', function () {
    $this->seed(PlanSeeder::class);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('features', fn ($features) => collect($features)->firstWhere('key', 'mobile-api') === null)
        ->where('tiers.3.features', fn ($features) => collect($features)->firstWhere('key', 'mobile-api') === null)
        ->where('tiers.3.key', 'pro'));
});

test('the page still renders sensibly if plans have not been seeded yet', function () {
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('tiers', []));
});

test('the marketing page never leaks another shop\'s data - it renders no shop-scoped content at all', function () {
    $this->get('/')->assertInertia(fn ($page) => $page
        ->missing('tiers.0.id')
        ->where('shop', null));
});

test('a paid tier shows how much it saves vs. buying its bundled features as add-ons', function () {
    $this->seed(PlanSeeder::class);

    // Every feature falls back to the same $20 placeholder price with no
    // feature_settings rows seeded - Solo bundles 2 features ($40
    // standalone) for $29, Start-up bundles 3 ($60 standalone) for $49,
    // Pro bundles all 7 ($140 standalone) for $99.
    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('tiers.1.monthlyPriceCents', 2900)
        ->where('tiers.1.savingsCents', 1100)
        ->where('tiers.2.monthlyPriceCents', 4900)
        ->where('tiers.2.savingsCents', 1100)
        ->where('tiers.3.monthlyPriceCents', 9900)
        ->where('tiers.3.savingsCents', 4100));
});

test('bundle savings reflect admin-edited feature prices, not the placeholder default', function () {
    $this->seed(PlanSeeder::class);
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::JobBoard->value)->update(['price_cents' => 5000]);

    // Start-up bundles Customers + QuoteBuilder + JobBoard: 2000 + 2000 +
    // 5000 = 9000 standalone, vs. its 4900 price.
    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('tiers.2.savingsCents', 4100));
});

test('the advertised trial length defaults to 14 days with no platform setting seeded', function () {
    $this->get('/')->assertInertia(fn ($page) => $page->where('trialDays', 14));
});

test('the advertised trial length reflects the admin-edited platform default, not a hardcoded number', function () {
    PlatformSetting::current()->update(['default_trial_days' => 21]);

    $this->get('/')->assertInertia(fn ($page) => $page->where('trialDays', 21));
});

test('a plan priced above its bundled features\' standalone value shows no savings', function () {
    $plan = Plan::factory()->create(['key' => 'start-up', 'monthly_price_cents' => 999999]);
    $plan->syncFeatures([Feature::Customers]);

    $this->get('/')->assertInertia(fn ($page) => $page->where('tiers.0.savingsCents', 0));
});
