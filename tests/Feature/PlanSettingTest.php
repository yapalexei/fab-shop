<?php

use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

afterEach(fn () => Carbon::setTestNow());

/** A valid payload for editing $plan, with $overrides applied. */
function planPayload(Plan $plan, array $overrides = []): array
{
    return [
        'name' => $plan->name,
        'monthly_price_cents' => $plan->monthly_price_cents,
        'user_limit' => $plan->user_limit,
        'extra_seat_price_cents' => $plan->extra_seat_price_cents,
        'features' => $plan->features()->map(fn (Feature $f) => $f->value)->all(),
        'position' => $plan->position,
        'expires_on' => $plan->expires_on?->toDateString(),
        'show_expiry' => $plan->show_expiry,
        ...$overrides,
    ];
}

function shopOn(Plan $plan): Shop
{
    return Shop::factory()->create(['plan_id' => $plan->id, 'trial_ends_at' => now()->subDay()]);
}

test('only a platform admin can reach or change plan tiers', function () {
    $this->seed(PlanSeeder::class);
    $plan = Plan::offeredByKey('pro');
    $owner = actingAsShopMember(Shop::factory()->create(), 'Owner');

    $this->actingAs($owner)->get(route('platform-admin.plans.index'))->assertForbidden();
    $this->actingAs($owner)->post(route('platform-admin.plans.store'), planPayload($plan, ['name' => 'Sneaky']))->assertForbidden();
    $this->actingAs($owner)->patch(route('platform-admin.plans.update', $plan), planPayload($plan, ['monthly_price_cents' => 1]))->assertForbidden();
    $this->actingAs($owner)->post(route('platform-admin.plans.retire', $plan))->assertForbidden();

    expect($plan->fresh()->monthly_price_cents)->toBe(11900)->and(Plan::count())->toBe(4);
});

test('the tier page lists every tier in order with subscriber counts and no history yet', function () {
    $this->seed(PlanSeeder::class);
    shopOn(Plan::offeredByKey('pro'));
    shopOn(Plan::offeredByKey('pro'));

    $this->actingAs(actingAsPlatformAdmin())->get(route('platform-admin.plans.index'))
        ->assertInertia(fn ($page) => $page
            ->component('PlatformAdmin/Plans')
            ->has('tiers', 4)
            ->where('tiers.0.key', 'free')
            ->where('tiers.0.protected', true)
            ->where('tiers.3.key', 'pro')
            ->where('tiers.3.subscribers', 2)
            ->where('tiers.3.status', 'offered')
            ->where('tiers.3.history', [])
            ->has('allFeatures', count(Feature::cases())));
});

test('an admin can add a tier, which then appears on the public list in its position', function () {
    $this->seed(PlanSeeder::class);

    $this->actingAs(actingAsPlatformAdmin())->post(route('platform-admin.plans.store'), [
        'name' => 'Studio Plus',
        'monthly_price_cents' => 8900,
        'user_limit' => 6,
        'extra_seat_price_cents' => 700,
        'features' => [Feature::Customers->value, Feature::JobBoard->value],
        'position' => 4,
        'show_expiry' => false,
    ])->assertRedirect(route('platform-admin.plans.index'));

    $plan = Plan::offeredByKey('studio-plus');

    expect($plan->monthly_price_cents)->toBe(8900)
        ->and($plan->version)->toBe(1)
        ->and($plan->features()->pluck('value')->sort()->values()->all())->toBe(['customers', 'job-board'])
        ->and(Plan::orderedByTier()->count())->toBe(5);

    $this->get('/')->assertInertia(fn ($page) => $page->has('tiers', 5)->where('tiers.4.key', 'studio-plus'));
});

test('a new tier\'s key never collides with an existing one', function () {
    $this->seed(PlanSeeder::class);
    $admin = actingAsPlatformAdmin();
    $payload = ['name' => 'Pro', 'monthly_price_cents' => 100, 'features' => []];

    $this->actingAs($admin)->post(route('platform-admin.plans.store'), $payload)->assertSessionHasNoErrors();

    expect(Plan::query()->pluck('key')->all())->toContain('pro', 'pro-2');
});

test('changing a tier\'s price publishes a new version and leaves existing subscribers on the old one', function () {
    $this->seed(PlanSeeder::class);
    $v1 = Plan::offeredByKey('start-up');
    $grandfathered = shopOn($v1);

    $this->actingAs(actingAsPlatformAdmin())
        ->patch(route('platform-admin.plans.update', $v1), planPayload($v1, ['monthly_price_cents' => 6900]))
        ->assertRedirect(route('platform-admin.plans.index'))
        ->assertSessionHas('status');

    $v2 = Plan::offeredByKey('start-up');

    expect($v2->id)->not->toBe($v1->id)
        ->and($v2->version)->toBe(2)
        ->and($v2->monthly_price_cents)->toBe(6900)
        ->and($v1->fresh()->superseded_at)->not->toBeNull()
        ->and($v1->fresh()->monthly_price_cents)->toBe(5900)
        // The shop is untouched and still reads the price and seats it signed up for.
        ->and($grandfathered->fresh()->plan_id)->toBe($v1->id)
        ->and($grandfathered->fresh()->plan->monthly_price_cents)->toBe(5900)
        ->and($v2->features()->count())->toBe($v1->features()->count())
        ->and(Plan::where('key', 'start-up')->count())->toBe(2)
        ->and(Plan::orderedByTier()->where('key', 'start-up')->count())->toBe(1);
});

test('seats, extra-seat price and features are versioned too, and the old version keeps its own', function () {
    $this->seed(PlanSeeder::class);
    $v1 = Plan::offeredByKey('solo');
    $shop = shopOn($v1);

    $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $v1), planPayload($v1, [
        'user_limit' => 2,
        'extra_seat_price_cents' => 500,
        'features' => [Feature::Customers->value, Feature::QuoteBuilder->value, Feature::JobBoard->value],
    ]))->assertSessionHasNoErrors();

    $v2 = Plan::offeredByKey('solo');

    expect($v2->user_limit)->toBe(2)
        ->and($v2->extra_seat_price_cents)->toBe(500)
        ->and($v2->hasFeature(Feature::JobBoard))->toBeTrue()
        ->and($v1->fresh()->user_limit)->toBe(1)
        ->and($v1->hasFeature(Feature::JobBoard))->toBeFalse()
        ->and($shop->fresh()->hasFeature(Feature::JobBoard))->toBeFalse()
        ->and($shop->fresh()->seatLimit())->toBe(1);
});

test('the tier\'s history lists the archived versions with their values and how many shops remain', function () {
    $this->seed(PlanSeeder::class);
    $v1 = Plan::offeredByKey('start-up');
    shopOn($v1);
    shopOn($v1);
    $admin = actingAsPlatformAdmin();

    $this->actingAs($admin)->patch(route('platform-admin.plans.update', $v1), planPayload($v1, ['monthly_price_cents' => 6900]));
    $v2 = Plan::offeredByKey('start-up');
    shopOn($v2);
    $this->actingAs($admin)->patch(route('platform-admin.plans.update', $v2), planPayload($v2, ['monthly_price_cents' => 7900]));

    $this->get(route('platform-admin.plans.index'))->assertInertia(fn ($page) => $page
        ->where('tiers.2.key', 'start-up')
        ->where('tiers.2.version', 3)
        ->where('tiers.2.monthly_price_cents', 7900)
        ->where('tiers.2.subscribers', 0)
        ->has('tiers.2.history', 2)
        ->where('tiers.2.history.0.version', 2)
        ->where('tiers.2.history.0.monthly_price_cents', 6900)
        ->where('tiers.2.history.0.subscribers', 1)
        ->where('tiers.2.history.1.version', 1)
        ->where('tiers.2.history.1.monthly_price_cents', 5900)
        ->where('tiers.2.history.1.subscribers', 2)
        ->where('tiers.2.history.1.archived_at', fn ($at) => $at !== null));
});

test('saving without changing anything a subscriber bought does not create a version', function () {
    $this->seed(PlanSeeder::class);
    $plan = Plan::offeredByKey('pro');

    $this->actingAs(actingAsPlatformAdmin())
        ->patch(route('platform-admin.plans.update', $plan), planPayload($plan))
        ->assertSessionHasNoErrors();

    expect(Plan::where('key', 'pro')->count())->toBe(1)->and($plan->fresh()->version)->toBe(1);
});

test('reordering and availability settings update the tier in place', function () {
    $this->seed(PlanSeeder::class);
    $plan = Plan::offeredByKey('pro');
    $shop = shopOn($plan);

    $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $plan), planPayload($plan, [
        'position' => 7,
        'expires_on' => now()->addMonth()->toDateString(),
        'show_expiry' => true,
    ]))->assertSessionHasNoErrors();

    expect(Plan::where('key', 'pro')->count())->toBe(1)
        ->and($plan->fresh()->position)->toBe(7)
        ->and($plan->fresh()->show_expiry)->toBeTrue()
        ->and($shop->fresh()->plan_id)->toBe($plan->id);
});

test('an archived version can no longer be edited', function () {
    $this->seed(PlanSeeder::class);
    $v1 = Plan::offeredByKey('pro');
    $admin = actingAsPlatformAdmin();
    $this->actingAs($admin)->patch(route('platform-admin.plans.update', $v1), planPayload($v1, ['monthly_price_cents' => 12900]));

    $this->actingAs($admin)->patch(route('platform-admin.plans.update', $v1), planPayload($v1, ['monthly_price_cents' => 1]))
        ->assertSessionHasErrors('plan');
    $this->actingAs($admin)->post(route('platform-admin.plans.retire', $v1))->assertSessionHasErrors('plan');

    expect($v1->fresh()->monthly_price_cents)->toBe(11900);
});

test('a tier with a future end date stays on the public list, and shows the date only if asked', function () {
    $this->seed(PlanSeeder::class);
    $plan = Plan::offeredByKey('solo');
    $until = now()->addDays(20)->toDateString();
    $admin = actingAsPlatformAdmin();

    $this->actingAs($admin)->patch(route('platform-admin.plans.update', $plan), planPayload($plan, ['expires_on' => $until, 'show_expiry' => false]));

    $this->get('/')->assertInertia(fn ($page) => $page->has('tiers', 4)->where('tiers.1.key', 'solo')->where('tiers.1.availableUntil', null));
    $this->actingAs($admin)->get(route('platform-admin.plans.index'))->assertInertia(fn ($page) => $page->where('tiers.1.status', 'expiring'));

    $this->actingAs($admin)->patch(route('platform-admin.plans.update', $plan), planPayload($plan, ['expires_on' => $until, 'show_expiry' => true]));

    $this->get('/')->assertInertia(fn ($page) => $page->where('tiers.1.availableUntil', $until));
});

test('once its end date arrives a tier leaves every public list but keeps its subscribers', function () {
    $this->seed(PlanSeeder::class);
    $plan = Plan::offeredByKey('solo');
    $shop = shopOn($plan);
    $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $plan), planPayload($plan, ['expires_on' => now()->addDays(3)->toDateString()]));

    expect(Plan::orderedByTier()->pluck('key')->all())->toContain('solo');

    Carbon::setTestNow(now()->addDays(3));

    expect(Plan::orderedByTier()->pluck('key')->all())->not->toContain('solo')
        ->and(Plan::offeredByKey('solo'))->toBeNull()
        ->and($shop->fresh()->plan_id)->toBe($plan->id)
        ->and($shop->fresh()->hasFeature(Feature::QuoteBuilder))->toBeTrue();

    $this->get('/')->assertInertia(fn ($page) => $page->has('tiers', 3));
});

test('a removed tier can no longer be chosen at signup', function () {
    $this->seed(PlanSeeder::class);
    $this->actingAs(actingAsPlatformAdmin())->post(route('platform-admin.plans.retire', Plan::offeredByKey('solo')));
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.shop'), ['name' => 'X', 'plan' => 'solo'])->assertSessionHasErrors('plan');
    $this->actingAs($user)->post(route('onboarding.shop'), ['name' => 'X', 'plan' => 'pro'])->assertSessionHasNoErrors();
});

test('retiring a tier takes it off the list now and shows as removed on the tier page', function () {
    $this->seed(PlanSeeder::class);
    $plan = Plan::offeredByKey('solo');
    $shop = shopOn($plan);
    $admin = actingAsPlatformAdmin();

    $this->actingAs($admin)->post(route('platform-admin.plans.retire', $plan))->assertSessionHasNoErrors();

    expect(Plan::offeredByKey('solo'))->toBeNull()->and($shop->fresh()->plan_id)->toBe($plan->id);

    $this->get(route('platform-admin.plans.index'))->assertInertia(fn ($page) => $page
        ->where('tiers.1.key', 'solo')
        ->where('tiers.1.status', 'removed')
        ->where('tiers.1.subscribers', 1));

    // Clearing the date puts it back.
    $this->patch(route('platform-admin.plans.update', $plan), planPayload($plan->fresh(), ['expires_on' => null]));
    expect(Plan::offeredByKey('solo'))->not->toBeNull();
});

test('the Free tier can be edited but never removed or given an end date', function () {
    $this->seed(PlanSeeder::class);
    $free = Plan::offeredByKey('free');
    $admin = actingAsPlatformAdmin();

    $this->actingAs($admin)->post(route('platform-admin.plans.retire', $free))->assertSessionHasErrors('plan');
    $this->actingAs($admin)->patch(route('platform-admin.plans.update', $free), planPayload($free, ['expires_on' => now()->addDay()->toDateString()]))
        ->assertSessionHasErrors('expires_on');
    $this->actingAs($admin)->patch(route('platform-admin.plans.update', $free), planPayload($free, ['user_limit' => 2]))
        ->assertSessionHasNoErrors();

    expect(Plan::offeredByKey('free')->user_limit)->toBe(2);
});

test('bad tier values are rejected', function (array $bad, string $field) {
    $this->seed(PlanSeeder::class);
    $plan = Plan::offeredByKey('pro');

    $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $plan), planPayload($plan, $bad))
        ->assertSessionHasErrors($field);

    expect(Plan::where('key', 'pro')->count())->toBe(1);
})->with([
    'negative price' => [['monthly_price_cents' => -1], 'monthly_price_cents'],
    'zero seats' => [['user_limit' => 0], 'user_limit'],
    'blank name' => [['name' => ''], 'name'],
    'unknown feature' => [['features' => ['nope']], 'features.0'],
    'malformed date' => [['expires_on' => '10/31/2026'], 'expires_on'],
]);

test('a shop on an old version can only move up the ladder, and annual-switch bills its own price', function () {
    $this->seed(PlanSeeder::class);
    $v1 = Plan::offeredByKey('start-up');
    $shop = shopOn($v1);
    $owner = actingAsShopMember($shop, 'Owner');
    $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $v1), planPayload($v1, ['monthly_price_cents' => 9900]));

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('currentPlanId', $v1->id)
        ->where('currentPlan.monthly_price_cents', 5900)
        ->where('plans', fn ($plans) => collect($plans)->firstWhere('key', 'start-up')['upgradeable'] === false
            && collect($plans)->firstWhere('key', 'pro')['upgradeable'] === true)
        ->where('annualSwitch.charge_cents', fn ($c) => $c === 5900 * 10));

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up', 'interval' => 'monthly'])->assertSessionHasErrors('plan');

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up', 'interval' => 'annual'])->assertSessionHasNoErrors();
    expect($shop->fresh()->plan_id)->toBe($v1->id)->and($shop->fresh()->billing_interval->value)->toBe('annual');

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'pro'])->assertRedirect(route('dashboard'));
    expect($shop->fresh()->plan->key)->toBe('pro');
});

test('a shop on a removed tier can still reach its plan page and upgrade', function () {
    $this->seed(PlanSeeder::class);
    $solo = Plan::offeredByKey('solo');
    $shop = shopOn($solo);
    $owner = actingAsShopMember($shop, 'Owner');
    $this->actingAs(actingAsPlatformAdmin())->post(route('platform-admin.plans.retire', $solo));

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('currentPlan.key', 'solo')
        ->where('plans', fn ($plans) => ! collect($plans)->contains('key', 'solo')
            && collect($plans)->firstWhere('key', 'start-up')['upgradeable'] === true));

    expect($shop->fresh()->canUpgrade())->toBeTrue();
    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up'])->assertRedirect(route('dashboard'));
});

test('the shop directory can show and filter by an old version a shop is still on', function () {
    $this->seed(PlanSeeder::class);
    $v1 = Plan::offeredByKey('start-up');
    $shop = shopOn($v1);
    $admin = actingAsPlatformAdmin();
    $this->actingAs($admin)->patch(route('platform-admin.plans.update', $v1), planPayload($v1, ['monthly_price_cents' => 6900]));

    $this->get(route('platform-admin.index'))->assertInertia(fn ($page) => $page
        ->where('plans', fn ($plans) => collect($plans)->contains('name', 'Shop (v1, archived)')
            && collect($plans)->contains('name', 'Shop'))
        ->where('shops.data.0.plan_name', 'Shop'));

    // Revenue still counts the shop at the price it actually pays.
    expect($shop->fresh()->plan->monthly_price_cents)->toBe(5900);
});

test('re-running the plan seeder only touches the current version', function () {
    $this->seed(PlanSeeder::class);
    $v1 = Plan::offeredByKey('pro');
    $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $v1), planPayload($v1, ['monthly_price_cents' => 12900]));

    $this->seed(PlanSeeder::class);

    expect(Plan::where('key', 'pro')->count())->toBe(2)
        ->and($v1->fresh()->monthly_price_cents)->toBe(11900)
        ->and($v1->fresh()->superseded_at)->not->toBeNull()
        ->and(Plan::offeredByKey('pro')->version)->toBe(2);
});

test('creating, changing and removing a tier are written to the security log', function () {
    $this->seed(PlanSeeder::class);
    $admin = actingAsPlatformAdmin();
    $plan = Plan::offeredByKey('solo');

    $this->actingAs($admin)->post(route('platform-admin.plans.store'), ['name' => 'Extra', 'monthly_price_cents' => 100, 'features' => []]);
    $this->actingAs($admin)->patch(route('platform-admin.plans.update', $plan), planPayload($plan, ['monthly_price_cents' => 3900]));
    $this->actingAs($admin)->post(route('platform-admin.plans.retire', Plan::offeredByKey('extra')));

    expect(Activity::query()->where('log_name', 'security')->where('causer_id', $admin->id)->pluck('description')->all())
        ->toBe([
            'platform admin: plan tier created',
            'platform admin: plan tier changed',
            'platform admin: plan tier removed from public list',
        ]);
});
