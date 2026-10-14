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
        // Solo includes quoting but not the job board; Start-up and Pro both
        // include it - matches PlanSeeder exactly, so this test breaks the
        // moment the two drift. (QuickBooks sync is a switched-off future
        // feature and so isn't listed at all.)
        ->where('tiers.1.features', fn ($features) => collect($features)->firstWhere('key', 'quote-builder')['included'] === true
            && collect($features)->firstWhere('key', 'job-board')['included'] === false)
        ->where('tiers.2.features', fn ($features) => collect($features)->firstWhere('key', 'job-board')['included'] === true
            && collect($features)->firstWhere('key', 'quickbooks-sync') === null)
        ->where('tiers.3.features', fn ($features) => collect($features)->firstWhere('key', 'job-board')['included'] === true));
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

    // No feature_settings rows seeded, so each feature falls back to its
    // default add-on price: Customers/Quote builder/Job board/AI assist/AI
    // agent $20, Quote history/emailing $10, Material tracking/Qualification
    // records $25. Solo bundles Customers + Quote builder + Emailing = $50
    // for $29; Shop adds Job board, History, AI assist = $100 for $59; Pro
    // bundles all 11 = $210 for $119 (adds Quickbooks/Mobile at $20 each, and
    // Material tracking/Qualification records at $25).
    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('tiers.1.monthlyPriceCents', 2900)
        ->where('tiers.1.savingsCents', 2100)
        ->where('tiers.2.monthlyPriceCents', 5900)
        ->where('tiers.2.savingsCents', 4100)
        ->where('tiers.3.monthlyPriceCents', 11900)
        ->where('tiers.3.savingsCents', 9100));
});

test('bundle savings reflect admin-edited feature prices, not the placeholder default', function () {
    $this->seed(PlanSeeder::class);
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::JobBoard->value)->update(['price_cents' => 5000]);

    // Shop bundles Customers + QuoteBuilder + QuoteEmailing + JobBoard +
    // QuoteHistory + AiAssist: 2000 + 2000 + 1000 + 5000 + 1000 + 2000 =
    // 13000 standalone, vs. its 5900 price.
    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('tiers.2.savingsCents', 7100));
});

test('the advertised trial length defaults to 30 days with no platform setting seeded', function () {
    $this->get('/')->assertInertia(fn ($page) => $page->where('trialDays', 30));
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

test('the marketing page ships its description, canonical, social tags and pricing structured data in the HTML shell', function () {
    $this->seed(PlanSeeder::class);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="description" content="Quoting, job tracking and invoicing')
        ->toContain('<link rel="canonical" href="'.url('/').'">')
        ->toContain('<meta property="og:title"')
        ->toContain('<meta name="twitter:card" content="summary">')
        ->toContain('<script type="application/ld+json" nonce="');

    preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $match);
    $data = json_decode($match[1], true);

    expect($data['@type'])->toBe('SoftwareApplication')
        ->and(collect($data['offers'])->pluck('name')->all())->toBe(['Free trial', 'Solo', 'Shop', 'Pro'])
        ->and($data['offers'][0]['price'])->toBe('0.00');
});

test('the legal pages carry a description and canonical link', function () {
    $html = $this->get('/terms')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="description" content="The terms for using')
        ->toContain('<link rel="canonical" href="'.url('/terms').'">');
});

test('pages without SEO data keep the plain app-name title and add no meta', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('<title inertia>'.config('app.name').'</title>')
        ->not->toContain('name="description"')
        ->not->toContain('rel="canonical"');
});
