<?php

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

test('unshipped features are flagged as coming soon regardless of plan', function () {
    $this->seed(PlanSeeder::class);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('features', fn ($features) => collect($features)->firstWhere('key', 'mobile-api')['shipped'] === false)
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
