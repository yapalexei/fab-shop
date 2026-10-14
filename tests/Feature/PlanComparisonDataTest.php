<?php

use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;
use Database\Seeders\PlanSeeder;

beforeEach(fn () => $this->seed(PlanSeeder::class));

function comparisonShop(string $plan = 'start-up', array $attributes = []): Shop
{
    return Shop::factory()->create([
        'plan_id' => Plan::offeredByKey($plan)->id,
        'trial_ends_at' => now()->subMonth(),
        ...$attributes,
    ]);
}

test('the landing page has what the comparison table needs for every tier', function () {
    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('annualMonthsCharged', config('shop.annual_months_charged'))
        ->has('tiers', 4)
        ->where('tiers', fn ($tiers) => collect($tiers)->every(fn ($tier) => array_key_exists('monthlyPriceCents', $tier)
            && array_key_exists('userLimit', $tier)
            && array_key_exists('extraSeatPriceCents', $tier)
            && count($tier['features']) === count(collect($tiers)->first()['features'])))
        // Solo has no job board; Shop and Pro do - the ticks the table draws.
        ->where('tiers.1.features', fn ($f) => collect($f)->firstWhere('key', 'job-board')['included'] === false)
        ->where('tiers.2.features', fn ($f) => collect($f)->firstWhere('key', 'job-board')['included'] === true));
});

test('a tier that leaves the public list also leaves the comparison', function () {
    $this->actingAs(actingAsPlatformAdmin())->post(route('platform-admin.plans.retire', Plan::offeredByKey('solo')));

    $this->get('/')->assertInertia(fn ($page) => $page->has('tiers', 3)
        ->where('tiers', fn ($tiers) => ! collect($tiers)->contains('key', 'solo')));
});

test('the upgrade page gives every plan its seats, extra-seat price and ticked features', function () {
    $owner = actingAsShopMember(comparisonShop(), 'Owner');

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('annualMonthsCharged', config('shop.annual_months_charged'))
        ->where('plans', function ($plans) {
            $plans = collect($plans)->keyBy('key');

            return $plans['solo']['user_limit'] === 1
                && $plans['solo']['extra_seat_price_cents'] === null
                && $plans['start-up']['extra_seat_price_cents'] === 900
                && in_array('job-board', $plans['start-up']['feature_keys'])
                && ! in_array('job-board', $plans['solo']['feature_keys'])
                && $plans['free']['feature_keys'] === [];
        }));
});

test('the calculator uses the live add-on prices, so an admin edit shows up', function () {
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->update(['price_cents' => 3300]);
    $owner = actingAsShopMember(comparisonShop(), 'Owner');

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('calculator.features', fn ($features) => collect($features)->firstWhere('key', 'material-tracking')['addonCents'] === 3300
            && collect($features)->firstWhere('key', 'quote-emailing')['addonCents'] === 1000));
});

test('the calculator starts from what the shop uses: its plan plus add-ons, not what a trial unlocks', function () {
    $shop = comparisonShop(attributes: ['trial_ends_at' => now()->addDays(5)]);
    ShopFeatureSubscription::query()->forceCreate(['shop_id' => $shop->id, 'feature' => Feature::QualificationRecords->value, 'started_at' => now()]);
    $owner = actingAsShopMember($shop->fresh(), 'Owner');

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('calculator.needed', fn ($needed) => collect($needed)->contains('job-board')
            && collect($needed)->contains('qualification-records')
            // Unlocked only by the trial, so not something they use today.
            && ! collect($needed)->contains('material-tracking'))
        ->where('calculator.people', $shop->seatsUsed()));
});

test('carried-over features count as the shop\'s own in the calculator and on its plan row', function () {
    $shop = comparisonShop('solo', ['extra_features' => [Feature::MaterialTracking->value]]);
    $owner = actingAsShopMember($shop->fresh(), 'Owner');

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('calculator.needed', fn ($needed) => collect($needed)->contains('material-tracking'))
        ->where('currentPlan.feature_keys', fn ($keys) => collect($keys)->contains('material-tracking') && collect($keys)->contains('quote-builder')));
});

test('a shop on an earlier version sees its own price beside the plans on offer', function () {
    $v1 = Plan::offeredByKey('start-up');
    $shop = comparisonShop();
    $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $v1), [
        'name' => 'Shop', 'monthly_price_cents' => 6900, 'user_limit' => 4, 'extra_seat_price_cents' => 1200,
        'features' => $v1->features()->map(fn (Feature $f) => $f->value)->all(), 'position' => 2,
    ]);
    $owner = actingAsShopMember($shop->fresh(), 'Owner');

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('currentPlan.monthly_price_cents', 5900)
        ->where('currentPlan.extra_seat_price_cents', 900)
        ->where('plans', fn ($plans) => collect($plans)->firstWhere('key', 'start-up')['monthly_price_cents'] === 6900));
});

test('a discontinued feature is not in the calculator or the comparison', function () {
    config(['shop.discontinued_features' => [Feature::AiAssist->value]]);
    $owner = actingAsShopMember(comparisonShop(), 'Owner');

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('calculator.features', fn ($f) => ! collect($f)->contains('key', 'ai-assist'))
        ->where('calculator.needed', fn ($n) => ! collect($n)->contains('ai-assist'))
        ->where('plans', fn ($plans) => ! collect($plans)->pluck('feature_keys')->flatten()->contains('ai-assist')));

    $this->get('/')->assertInertia(fn ($page) => $page->where('features', fn ($f) => ! collect($f)->contains('key', 'ai-assist')));
});
