<?php

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;
use App\Support\Billing;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-10 12:00:00');
    $this->seed(PlanSeeder::class);
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->update(['price_cents' => 1000]);
});

afterEach(fn () => Carbon::setTestNow());

function billedShop(string $plan = 'start-up', ?string $renewsAt = null, string $interval = 'monthly'): Shop
{
    return Shop::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'plan_id' => Plan::where('key', $plan)->value('id'),
        'subscription_renews_at' => $renewsAt,
        'billing_interval' => $interval,
    ]);
}

test('an annual period is charged as the configured number of months', function () {
    expect(Billing::periodPriceCents(1000, BillingInterval::Monthly))->toBe(1000)
        ->and(Billing::periodPriceCents(1000, BillingInterval::Annual))->toBe(10_000);
});

test('with no live billing period a purchase starts one and charges in full', function () {
    $quote = Billing::addOnQuote(billedShop(), 1000);

    expect($quote['charge_cents'])->toBe(1000)
        ->and($quote['starts_new_period'])->toBeTrue()
        ->and($quote['prorated'])->toBeFalse()
        ->and(Carbon::parse($quote['renews_at'])->toDateString())->toBe('2026-10-10');

    $annual = Billing::addOnQuote(billedShop(), 1000, BillingInterval::Annual);

    expect($annual['charge_cents'])->toBe(10_000)
        ->and(Carbon::parse($annual['renews_at'])->toDateString())->toBe('2027-09-10');
});

test('mid-period an add-on is charged only for the days left, then in full at renewal', function () {
    // Period ran Aug 25 - Sep 25; 15 of its 31 days remain.
    $quote = Billing::addOnQuote(billedShop(renewsAt: '2026-09-25 12:00:00'), 1000);

    expect($quote['charge_cents'])->toBe(484)
        ->and($quote['period_price_cents'])->toBe(1000)
        ->and($quote['prorated'])->toBeTrue()
        ->and($quote['days_left'])->toBe(15)
        ->and($quote['starts_new_period'])->toBeFalse()
        ->and(Carbon::parse($quote['renews_at'])->toDateString())->toBe('2026-09-25');
});

test('subscribing starts the shop\'s billing period, and later add-ons join it', function () {
    $shop = billedShop();
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->post(route('shop.addons.store', Feature::MaterialTracking->value), ['confirm_separate' => true, 'interval' => 'annual'])
        ->assertSessionHasNoErrors();

    $shop->refresh();
    expect($shop->billingInterval())->toBe(BillingInterval::Annual)
        ->and($shop->subscription_renews_at->toDateString())->toBe('2027-09-10');

    Carbon::setTestNow('2027-03-10 12:00:00');
    $this->actingAs($owner)->post(route('shop.addons.store', Feature::QualificationRecords->value), ['confirm_separate' => true]);

    // The renewal date doesn't move: the second add-on shares the running period.
    expect($shop->fresh()->subscription_renews_at->toDateString())->toBe('2027-09-10');
});

test('cancelling keeps the add-on until the period ends, and keeping it again is free', function () {
    $shop = billedShop(renewsAt: '2026-09-25 12:00:00');
    ShopFeatureSubscription::query()->forceCreate(['shop_id' => $shop->id, 'feature' => Feature::MaterialTracking->value, 'started_at' => now()->subDays(5)]);
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->delete(route('shop.addons.destroy', Feature::MaterialTracking->value));

    $sub = ShopFeatureSubscription::query()->sole();
    expect($sub->cancelled_at)->not->toBeNull()
        ->and($sub->ends_at->toDateString())->toBe('2026-09-25')
        ->and($shop->fresh()->hasFeature(Feature::MaterialTracking))->toBeTrue();

    $this->actingAs($owner)->post(route('shop.addons.store', Feature::MaterialTracking->value))->assertSessionHas('status');
    expect($sub->fresh()->cancelled_at)->toBeNull();

    $this->actingAs($owner)->delete(route('shop.addons.destroy', Feature::MaterialTracking->value));
    Carbon::setTestNow('2026-09-26 00:00:00');
    expect($shop->fresh()->hasFeature(Feature::MaterialTracking))->toBeFalse();
});

test('cancelling with no running period ends access immediately', function () {
    $shop = billedShop();
    ShopFeatureSubscription::query()->forceCreate(['shop_id' => $shop->id, 'feature' => Feature::MaterialTracking->value, 'started_at' => now()]);

    $this->actingAs(actingAsShopMember($shop))->delete(route('shop.addons.destroy', Feature::MaterialTracking->value));

    expect($shop->fresh()->hasFeature(Feature::MaterialTracking))->toBeFalse();
});

test('an upgrade mid-period charges the price difference for the days left', function () {
    $shop = billedShop('start-up', '2026-09-25 12:00:00');

    $quote = Billing::planChangeQuote($shop, Plan::where('key', 'pro')->first(), BillingInterval::Monthly);

    // $99 - $49 = $50, 15/31 of it.
    expect($quote['charge_cents'])->toBe(2419)
        ->and($quote['period_price_cents'])->toBe(9900)
        ->and($quote['starts_new_period'])->toBeFalse();
});

test('an upgrade credits an add-on the new plan now bundles', function () {
    $shop = billedShop('start-up', '2026-09-25 12:00:00');
    ShopFeatureSubscription::query()->forceCreate(['shop_id' => $shop->id, 'feature' => Feature::MaterialTracking->value, 'started_at' => now()]);

    $quote = Billing::planChangeQuote($shop->fresh(), Plan::where('key', 'pro')->first(), BillingInterval::Monthly);

    // 2419 for the plan difference less 484 for the dropped $10 add-on.
    expect($quote['charge_cents'])->toBe(1935);
});

test('switching the same plan from monthly to annual credits the unused month', function () {
    $shop = billedShop('start-up', '2026-09-25 12:00:00');

    $quote = Billing::planChangeQuote($shop, Plan::where('key', 'start-up')->first(), BillingInterval::Annual);

    // $490 for the year, less 15/31 of the $49 already paid for this month.
    expect($quote['charge_cents'])->toBe(49_000 - 2371)
        ->and($quote['starts_new_period'])->toBeTrue()
        ->and(Carbon::parse($quote['renews_at'])->toDateString())->toBe('2027-09-10');
});

test('the owner can upgrade with an interval, and only upward', function () {
    $shop = billedShop('start-up');
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'solo'])->assertSessionHasErrors('plan');
    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up', 'interval' => 'monthly'])->assertSessionHasErrors('plan');

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'pro', 'interval' => 'annual'])->assertSessionHasNoErrors();

    $shop->refresh();
    expect($shop->plan->key)->toBe('pro')
        ->and($shop->billingInterval())->toBe(BillingInterval::Annual)
        ->and($shop->subscription_renews_at->toDateString())->toBe('2027-09-10');
});

test('the same plan can move from monthly to annual but not back', function () {
    $shop = billedShop('start-up', '2026-09-25 12:00:00');
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up', 'interval' => 'annual'])->assertSessionHasNoErrors();
    expect($shop->fresh()->billingInterval())->toBe(BillingInterval::Annual);

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up', 'interval' => 'monthly'])->assertSessionHasErrors('plan');
});

test('the add-ons and plan pages carry the quotes the dialogs show', function () {
    $shop = billedShop('start-up', '2026-09-25 12:00:00');
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->get(route('shop.addons.index'))->assertInertia(fn ($page) => $page
        ->where('hasBillingPeriod', true)
        ->where('annualMonthsCharged', 10)
        ->where('addons', fn ($addons) => collect($addons)->firstWhere('feature', 'material-tracking')['quotes']['monthly']['charge_cents'] === 484));

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('billingInterval', 'monthly')
        ->where('annualSwitch.starts_new_period', true)
        ->where('plans', fn ($plans) => collect($plans)->firstWhere('key', 'pro')['quotes']['monthly']['charge_cents'] === 2419));
});
