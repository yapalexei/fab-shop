<?php

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;
use App\Notifications\PlanUpdated;
use Database\Seeders\PlanSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Carbon::setTestNow('2026-10-13 12:00:00');
});

afterEach(fn () => Carbon::setTestNow());

/** A shop on $key's current version, paid through $renewsInDays from now (null = not in a paid period). */
function tierShop(string $key = 'start-up', ?int $renewsInDays = 10, array $attributes = []): Shop
{
    return Shop::factory()->create([
        'plan_id' => Plan::offeredByKey($key)->id,
        'trial_ends_at' => now()->subMonth(),
        'subscription_renews_at' => $renewsInDays === null ? null : now()->addDays($renewsInDays),
        ...$attributes,
    ]);
}

/** Edit the tier as a platform admin; $changes override the tier's current values. */
function editTier(string $key, array $changes): void
{
    $plan = Plan::offeredByKey($key);

    test()->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $plan), [
        'name' => $plan->name,
        'monthly_price_cents' => $plan->monthly_price_cents,
        'user_limit' => $plan->user_limit,
        'extra_seat_price_cents' => $plan->extra_seat_price_cents,
        'features' => $plan->features()->map(fn (Feature $f) => $f->value)->all(),
        'position' => $plan->position,
        ...$changes,
    ])->assertSessionHasNoErrors();
}

/** Raise the Shop tier to $cents and apply it to subscribers from $on (null = immediately). */
function raiseTier(int $cents, ?string $on, string $key = 'start-up'): void
{
    editTier($key, ['monthly_price_cents' => $cents, 'apply_to_subscribers' => true, 'subscribers_effective_on' => $on]);
}

function moreFeatures(string $key = 'start-up'): array
{
    return [...Plan::offeredByKey($key)->features()->map(fn (Feature $f) => $f->value)->all(), Feature::MaterialTracking->value];
}

describe('a lower price', function () {
    test('applies to every shop on the tier straight away, with nothing for them to do', function () {
        Notification::fake();
        $paying = tierShop();
        $trial = tierShop(renewsInDays: null, attributes: ['trial_ends_at' => now()->addDays(5)]);
        $v1 = $paying->plan;

        editTier('start-up', ['monthly_price_cents' => 3000]);

        $v2 = Plan::offeredByKey('start-up');
        foreach ([$paying, $trial] as $shop) {
            $shop->refresh();
            expect($shop->plan_id)->toBe($v2->id)
                ->and($shop->plan->monthly_price_cents)->toBe(3000)
                ->and($shop->pending_plan_id)->toBeNull();
        }

        Notification::assertSentTo($paying->owner, PlanUpdated::class, fn (PlanUpdated $n) => $n->changes['price']['from_cents'] === $v1->monthly_price_cents
            && $n->changes['price']['to_cents'] === 3000
            && Carbon::parse($n->changes['price']['effective_at'])->lte(now()));
        Notification::assertCount(2);
    });

    test('also reaches a shop that was already on an old version before this rule existed', function () {
        Notification::fake();
        $shop = tierShop();
        $v1 = $shop->plan;
        editTier('start-up', ['monthly_price_cents' => 3000]);

        // As if the shop had been left behind by an earlier publish.
        Shop::query()->whereKey($shop->id)->update(['plan_id' => $v1->id, 'plan_notice' => null]);
        expect($shop->fresh()->plan->monthly_price_cents)->toBe(5900);

        $this->artisan('app:apply-scheduled-plan-changes')->assertSuccessful();

        expect($shop->fresh()->plan->monthly_price_cents)->toBe(3000);
        // Running it again changes nothing and tells nobody twice.
        Notification::fake();
        $this->artisan('app:apply-scheduled-plan-changes')->assertSuccessful();
        Notification::assertNothingSent();
    });

    test('is not replaced by a dearer one a shop was promised, and clears a scheduled increase', function () {
        $shop = tierShop();
        raiseTier(6900, now()->addDays(15)->toDateString());
        expect($shop->fresh()->pending_plan_id)->not->toBeNull();

        editTier('start-up', ['monthly_price_cents' => 4900]);

        $shop->refresh();
        expect($shop->plan->monthly_price_cents)->toBe(4900)->and($shop->pending_plan_id)->toBeNull();
    });

    test('moves a shop only if it keeps its seats', function () {
        Notification::fake();
        $shop = tierShop();
        $withExtras = tierShop(attributes: ['extra_seats' => 2]);

        $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', Plan::offeredByKey('start-up')), [
            'name' => 'Shop', 'monthly_price_cents' => 4900, 'user_limit' => 2, 'extra_seat_price_cents' => 900,
            'features' => moreFeatures(), 'position' => 2,
        ])->assertSessionHas('status', fn ($status) => str_contains($status, '2 shops stay on their earlier version'));

        expect($shop->fresh()->plan->monthly_price_cents)->toBe(5900)
            ->and($shop->fresh()->hasFeature(Feature::MaterialTracking))->toBeTrue();
        // Told about the feature, not about a price that isn't coming.
        Notification::assertSentTo($shop->owner, PlanUpdated::class, fn (PlanUpdated $n) => $n->changes['added'] === [Feature::MaterialTracking->value] && $n->changes['price'] === null);
        expect($withExtras->fresh()->plan->monthly_price_cents)->toBe(5900);
    });

    test('never moves a shop to dearer extra seats', function () {
        $withExtras = tierShop(attributes: ['extra_seats' => 2]);
        $without = tierShop();

        editTier('start-up', ['monthly_price_cents' => 4900, 'extra_seat_price_cents' => 1200]);

        expect($withExtras->fresh()->plan->monthly_price_cents)->toBe(5900)
            ->and($without->fresh()->plan->monthly_price_cents)->toBe(4900);
    });
});

describe('a higher price', function () {
    test('does not reach shops unless the admin says so, and nobody is emailed', function () {
        Notification::fake();
        $shop = tierShop();

        editTier('start-up', ['monthly_price_cents' => 7900, 'apply_to_subscribers' => false]);

        $shop->refresh();
        expect($shop->plan->monthly_price_cents)->toBe(5900)->and($shop->pending_plan_id)->toBeNull();
        Notification::assertNothingSent();

        Carbon::setTestNow(now()->addMonths(2));
        $this->artisan('app:apply-scheduled-plan-changes');
        expect($shop->fresh()->plan->monthly_price_cents)->toBe(5900);
    });

    test('is announced ahead of its date and applies when the date arrives', function () {
        Notification::fake();
        $shop = tierShop();
        $v1 = $shop->plan;
        $on = now()->addDays(15)->toDateString();

        raiseTier(6900, $on);

        $v2 = Plan::offeredByKey('start-up');
        $shop->refresh();
        expect($shop->plan_id)->toBe($v1->id)
            ->and($shop->plan->monthly_price_cents)->toBe(5900)
            ->and($shop->pending_plan_id)->toBe($v2->id)
            ->and($shop->pending_plan_effective_at->setTimezone($shop->localTimezone())->toDateString())->toBe($on)
            ->and($shop->pending_plan_effective_at->setTimezone($shop->localTimezone())->format('H:i'))->toBe('00:00');

        Notification::assertSentTo($shop->owner, PlanUpdated::class, fn (PlanUpdated $n) => $n->changes['price']['from_cents'] === 5900
            && $n->changes['price']['to_cents'] === 6900
            && Carbon::parse($n->changes['price']['effective_at'])->isFuture());
        Notification::assertCount(1);

        // The day before: still the old price.
        Carbon::setTestNow(now()->addDays(14)->setTime(23, 0));
        $this->artisan('app:apply-scheduled-plan-changes');
        expect($shop->fresh()->plan->monthly_price_cents)->toBe(5900);

        Carbon::setTestNow(now()->addDays(2));
        $this->artisan('app:apply-scheduled-plan-changes');

        $shop->refresh();
        expect($shop->plan->monthly_price_cents)->toBe(6900)->and($shop->pending_plan_id)->toBeNull();
    });

    test('with no date applies immediately', function () {
        Notification::fake();
        $shop = tierShop();

        raiseTier(6900, null);

        expect($shop->fresh()->plan->monthly_price_cents)->toBe(6900)->and($shop->fresh()->pending_plan_id)->toBeNull();
        Notification::assertSentTo($shop->owner, PlanUpdated::class, fn (PlanUpdated $n) => $n->changes['price']['to_cents'] === 6900
            && Carbon::parse($n->changes['price']['effective_at'])->lte(now()));
    });

    test('cannot be given a date in the past', function () {
        tierShop();
        $plan = Plan::offeredByKey('start-up');

        $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $plan), [
            'name' => 'Shop', 'monthly_price_cents' => 6900, 'user_limit' => 4, 'extra_seat_price_cents' => 900,
            'features' => [], 'position' => 2, 'apply_to_subscribers' => true,
            'subscribers_effective_on' => now()->subDay()->toDateString(),
        ])->assertSessionHasErrors('subscribers_effective_on');

        expect(Plan::offeredByKey('start-up')->id)->toBe($plan->id);
    });

    test('is only applied from the date chosen when raised again, with a fresh notice', function () {
        Notification::fake();
        $shop = tierShop();
        raiseTier(6900, now()->addDays(15)->toDateString());
        $v2 = Plan::offeredByKey('start-up');

        raiseTier(6400, now()->addDays(20)->toDateString());

        $shop->refresh();
        expect($shop->pending_plan_id)->not->toBe($v2->id)
            ->and($shop->pendingPlan->monthly_price_cents)->toBe(6400);
        Notification::assertCount(2);
    });

    test('is called off if the price goes back to what the shop pays', function () {
        $shop = tierShop();
        raiseTier(6900, now()->addDays(15)->toDateString());
        expect($shop->fresh()->pending_plan_id)->not->toBeNull();

        editTier('start-up', ['monthly_price_cents' => 5900]);

        $shop->refresh();
        expect($shop->pending_plan_id)->toBeNull()->and($shop->plan->monthly_price_cents)->toBe(5900);
    });

    test('never moves a shop that would lose seats', function () {
        $shop = tierShop();
        $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', Plan::offeredByKey('start-up')), [
            'name' => 'Shop', 'monthly_price_cents' => 6900, 'user_limit' => 2, 'extra_seat_price_cents' => 900,
            'features' => moreFeatures(), 'position' => 2, 'apply_to_subscribers' => true, 'subscribers_effective_on' => null,
        ])->assertSessionHas('status', fn ($status) => str_contains($status, '1 shop stay on their earlier version'));

        expect($shop->fresh()->plan->monthly_price_cents)->toBe(5900);
    });

    test('keeps every feature the shop had when it moves', function () {
        $shop = tierShop();
        editTier('start-up', ['monthly_price_cents' => 6900, 'features' => [Feature::Customers->value], 'apply_to_subscribers' => true, 'subscribers_effective_on' => null]);

        $shop->refresh();
        expect($shop->plan->version)->toBe(2)->and($shop->hasFeature(Feature::AiAssist))->toBeTrue()->and($shop->hasFeature(Feature::JobBoard))->toBeTrue();
    });
});

describe('the Free tier', function () {
    test('never moves anyone on a price change - it is not something they subscribed to', function () {
        $shop = Shop::factory()->create(['plan_id' => Plan::offeredByKey('free')->id, 'trial_ends_at' => now()->subMonth()]);

        editTier('free', ['monthly_price_cents' => 500, 'apply_to_subscribers' => true, 'subscribers_effective_on' => null]);

        expect($shop->fresh()->plan->monthly_price_cents)->toBe(0);
    });
});

test('the email says what changed and what the shop needs to do about it', function () {
    $shop = tierShop();
    $in = now()->addDays(15)->toIso8601String();
    $mail = fn (array $changes) => (new PlanUpdated($shop, ['plan_name' => 'Shop', 'added' => [], 'retired' => [], 'price' => null, 'seats' => null, ...$changes]))->toMail($shop->owner);
    $text = fn ($m) => implode("\n", array_merge($m->introLines, $m->outroLines));

    $lower = $mail(['price' => ['from_cents' => 5900, 'to_cents' => 3000, 'effective_at' => now()->toIso8601String()]]);
    expect($lower->subject)->toBe('Good news: your Shop plan is cheaper')
        ->and($text($lower))->toContain('$59')->toContain('$30')->toContain('already in effect')->toContain("don't need to do anything")
        ->and($text($lower))->toContain("aren't adjusted")->not->toContain('cancel');

    $ahead = $mail(['price' => ['from_cents' => 5900, 'to_cents' => 6900, 'effective_at' => $in]]);
    expect($ahead->subject)->toBe('Your Shop plan price is changing on October 28, 2026')
        ->and($text($ahead))->toContain('go up from $59 to $69 per month on October 28, 2026')
        ->and($text($ahead))->toContain('first payment on or after October 28, 2026')
        ->and($text($ahead))->toContain('cancel before October 28, 2026')->toContain("won't be charged it")
        ->and($text($ahead))->toContain('Price changes')->toContain(route('legal.terms'));

    $now = $mail(['price' => ['from_cents' => 5900, 'to_cents' => 6900, 'effective_at' => now()->toIso8601String()]]);
    expect($now->subject)->toBe('Your Shop plan price has changed')->and($text($now))->toContain('effective now');

    $annual = tierShop(attributes: ['billing_interval' => BillingInterval::Annual]);
    $m = (new PlanUpdated($annual, ['plan_name' => 'Shop', 'added' => [], 'retired' => [], 'price' => ['from_cents' => 5900, 'to_cents' => 6900, 'effective_at' => $in], 'seats' => null]))->toMail($annual->owner);
    expect(implode(' ', $m->introLines))->toContain('per year')->toContain('$590')->toContain('$690');
});

test('the email lists new features and seat changes with the price', function () {
    $shop = tierShop();
    $price = ['from_cents' => 5900, 'to_cents' => 6900, 'effective_at' => now()->addDays(15)->toIso8601String()];
    $m = (new PlanUpdated($shop, ['plan_name' => 'Shop', 'added' => [Feature::MaterialTracking->value, Feature::QualificationRecords->value], 'retired' => [], 'price' => $price, 'seats' => ['from' => 4, 'to' => 6]]))->toMail($shop->owner);
    $text = implode("\n", $m->introLines);

    expect($text)->toContain('Seats included:** 4 → 6, from October 28, 2026')
        ->and($text)->toContain('on top of what you had')
        ->and($text)->toContain(Feature::MaterialTracking->label())->toContain(Feature::MaterialTracking->tagline())
        ->and($text)->toContain(Feature::QualificationRecords->label());
});

test('a features-only email leads with what is new and says nothing about price', function () {
    $shop = tierShop();
    $mail = (new PlanUpdated($shop, ['plan_name' => 'Shop', 'added' => [Feature::AiAgent->value], 'retired' => [], 'price' => null, 'seats' => null]))->toMail($shop->owner);
    $text = implode("\n", $mail->introLines);

    expect($mail->subject)->toBe('More is included in your Shop plan')
        ->and($text)->toContain(Feature::AiAgent->label())->not->toContain('**Price:**');
});

test('shops keep every feature they had, even one the new version drops', function () {
    $shop = tierShop();
    $kept = Plan::offeredByKey('start-up')->features()->reject(fn (Feature $f) => $f === Feature::AiAssist)->map(fn (Feature $f) => $f->value)->all();

    editTier('start-up', ['monthly_price_cents' => 4900, 'features' => $kept]);

    $shop->refresh();
    expect($shop->plan->version)->toBe(2)
        ->and($shop->hasFeature(Feature::AiAssist))->toBeTrue()
        ->and(Plan::offeredByKey('start-up')->hasFeature(Feature::AiAssist))->toBeFalse()
        ->and($shop->extraFeatures()->all())->toBe([Feature::AiAssist])
        ->and($shop->planFeatures())->toContain(Feature::AiAssist);
});

test('features added to the tier reach existing shops at once, whatever happens to the price', function () {
    Notification::fake();
    $kept = tierShop();
    $lowered = tierShop();

    expect($kept->hasFeature(Feature::MaterialTracking))->toBeFalse();

    // Price up and not applied to subscribers: they keep their price and gain the feature.
    editTier('start-up', ['monthly_price_cents' => 7900, 'features' => moreFeatures(), 'apply_to_subscribers' => false]);

    expect($kept->fresh()->hasFeature(Feature::MaterialTracking))->toBeTrue()
        ->and($kept->fresh()->plan->monthly_price_cents)->toBe(5900);
    Notification::assertSentTo([$kept->owner, $lowered->owner], PlanUpdated::class, fn (PlanUpdated $n) => $n->changes['added'] === [Feature::MaterialTracking->value] && $n->changes['price'] === null);
    expect($kept->fresh()->plan_notice['added'])->toBe([Feature::MaterialTracking->value]);
});

test('a shop keeps its features through two successive edits', function () {
    $shop = tierShop();
    editTier('start-up', ['monthly_price_cents' => 4900, 'features' => [Feature::Customers->value]]);
    editTier('start-up', ['monthly_price_cents' => 4500, 'features' => [Feature::Customers->value, Feature::MaterialTracking->value]]);

    $shop->refresh();
    expect($shop->plan->monthly_price_cents)->toBe(4500)
        ->and($shop->hasFeature(Feature::JobBoard))->toBeTrue()
        ->and($shop->hasFeature(Feature::AiAssist))->toBeTrue()
        ->and($shop->hasFeature(Feature::MaterialTracking))->toBeTrue();
});

test('only the edited tier\'s shops are affected', function () {
    Notification::fake();
    $other = tierShop('pro');
    $solo = tierShop('solo');

    editTier('start-up', ['monthly_price_cents' => 4900, 'features' => [Feature::Customers->value]]);

    expect($other->fresh()->plan->monthly_price_cents)->toBe(11900)
        ->and($other->fresh()->extra_features)->toBeNull()
        ->and($solo->fresh()->plan->monthly_price_cents)->toBe(2900);
    Notification::assertNothingSent();
});

test('choosing a plan by hand drops a scheduled increase and any carried-over features', function () {
    $shop = tierShop();
    raiseTier(6900, now()->addDays(15)->toDateString());
    $shop->forceFill(['extra_features' => [Feature::MaterialTracking->value]])->save();
    expect($shop->fresh()->pending_plan_id)->not->toBeNull();

    $owner = actingAsShopMember($shop->fresh(), 'Owner');
    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'pro'])->assertSessionHasNoErrors();

    $shop->refresh();
    expect($shop->plan->key)->toBe('pro')
        ->and($shop->pending_plan_id)->toBeNull()
        ->and($shop->pending_plan_effective_at)->toBeNull()
        ->and($shop->extra_features)->toBeNull();
});

test('an add-on the lower-priced version now bundles stops being billed', function () {
    $shop = tierShop(renewsInDays: null);
    $addon = ShopFeatureSubscription::query()->forceCreate(['shop_id' => $shop->id, 'feature' => Feature::MaterialTracking->value, 'started_at' => now()]);

    editTier('start-up', ['monthly_price_cents' => 4900, 'features' => moreFeatures()]);

    expect($shop->fresh()->plan->version)->toBe(2)->and($addon->fresh()->cancelled_at)->not->toBeNull();
});

test('the plan page warns the owner of an increase ahead of its date', function () {
    $shop = tierShop();
    $on = now()->addDays(15)->toDateString();
    raiseTier(6900, $on);
    $owner = actingAsShopMember($shop->fresh(), 'Owner');

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('pendingChange.plan_id', Plan::offeredByKey('start-up')->id)
        ->where('pendingChange.from_monthly_cents', 5900)
        ->where('pendingChange.monthly_price_cents', 6900)
        ->where('pendingChange.effective_at', fn ($at) => Carbon::parse($at)->setTimezone($shop->localTimezone())->toDateString() === $on)
        ->where('currentPlan.monthly_price_cents', 5900)
        ->where('currentPlan.features', fn ($features) => collect($features)->contains(Feature::JobBoard->label())));
});

test('after a lower price the plan the shop is on is the one the page marks as current', function () {
    $shop = tierShop();
    editTier('start-up', ['monthly_price_cents' => 4900]);
    $owner = actingAsShopMember($shop->fresh(), 'Owner');

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('currentPlanId', Plan::offeredByKey('start-up')->id)
        ->where('pendingChange', null)
        ->where('currentPlan.monthly_price_cents', 4900)
        ->where('planNotice.price.to_cents', 4900));
});

test('the admin is told what a published change did to existing shops', function () {
    tierShop();
    tierShop(renewsInDays: null);
    $plan = Plan::offeredByKey('start-up');
    $body = fn (int $cents, array $extra = []) => [
        'name' => 'Shop', 'monthly_price_cents' => $cents, 'user_limit' => 4, 'extra_seat_price_cents' => 900,
        'features' => moreFeatures(), 'position' => 2, ...$extra,
    ];

    $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $plan), $body(4900))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Published version 2')
            && str_contains($status, '2 shops moved to the new price now and have been emailed')
            && str_contains($status, '2 shops gained the features'));

    $plan = Plan::offeredByKey('start-up');
    tierShop();
    $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $plan), $body(7000, ['apply_to_subscribers' => true, 'subscribers_effective_on' => now()->addDays(15)->toDateString()]))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'will move to the new price on Oct 28, 2026')
            && str_contains($status, 'emailed today'));

    $plan = Plan::offeredByKey('start-up');
    tierShop();
    $this->actingAs(actingAsPlatformAdmin())->patch(route('platform-admin.plans.update', $plan), $body(8000, ['apply_to_subscribers' => false]))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'keep the lower price they signed up for'));
});

test('the command is scheduled hourly', function () {
    expect(collect(app(Schedule::class)->events())
        ->contains(fn ($event) => str_contains($event->command, 'app:apply-scheduled-plan-changes') && $event->expression === '0 * * * *'))->toBeTrue();
});

test('the Plan page shows what changed until the owner dismisses it', function () {
    $shop = tierShop();
    editTier('start-up', ['monthly_price_cents' => 4900, 'features' => moreFeatures()]);
    $owner = actingAsShopMember($shop->fresh(), 'Owner');

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('planNotice.plan_name', 'Shop')
        ->where('planNotice.added.0.label', Feature::MaterialTracking->label())
        ->where('planNotice.added.0.tagline', Feature::MaterialTracking->tagline())
        ->where('planNotice.price.to_cents', 4900)
        ->where('planNotice.retired', []));

    $this->actingAs($owner)->delete(route('shop.plan.notice.dismiss'))->assertRedirect();

    expect($shop->fresh()->plan_notice)->toBeNull();
    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page->where('planNotice', null));
});

test('only the owner can dismiss the notice, and only their own shop\'s', function () {
    $shop = tierShop();
    $other = tierShop();
    editTier('start-up', ['features' => moreFeatures()]);

    $staff = actingAsShopMember($shop->fresh(), 'Staff');
    $this->actingAs($staff)->delete(route('shop.plan.notice.dismiss'))->assertForbidden();

    $owner = actingAsShopMember($shop->fresh(), 'Owner');
    $this->actingAs($owner)->delete(route('shop.plan.notice.dismiss'));

    expect($shop->fresh()->plan_notice)->toBeNull()->and($other->fresh()->plan_notice)->not->toBeNull();
});

test('changing plan by hand clears the old notice', function () {
    $shop = tierShop();
    editTier('start-up', ['features' => moreFeatures()]);
    expect($shop->fresh()->plan_notice)->not->toBeNull();

    $this->actingAs(actingAsShopMember($shop->fresh(), 'Owner'))->post(route('shop.plan.update'), ['plan' => 'pro']);

    expect($shop->fresh()->plan_notice)->toBeNull();
});

describe('a feature discontinued for good', function () {
    beforeEach(fn () => config(['shop.discontinued_features' => [Feature::AiAssist->value]]));

    test('nobody has it any more, however they got it', function () {
        $inPlan = tierShop();
        $carried = tierShop('solo', attributes: ['extra_features' => [Feature::AiAssist->value]]);
        $addon = tierShop('solo');
        ShopFeatureSubscription::query()->forceCreate(['shop_id' => $addon->id, 'feature' => Feature::AiAssist->value, 'started_at' => now()]);
        $trial = Shop::factory()->create(['trial_ends_at' => now()->addDays(5)]);

        foreach ([$inPlan, $carried, $addon, $trial] as $shop) {
            expect($shop->fresh()->hasFeature(Feature::AiAssist))->toBeFalse()
                ->and($shop->fresh()->enabledFeatures())->not->toContain(Feature::AiAssist)
                ->and($shop->fresh()->planFeatures())->not->toContain(Feature::AiAssist);
        }

        expect($inPlan->fresh()->hasFeature(Feature::JobBoard))->toBeTrue()
            ->and(Feature::AiAssist->isShipped())->toBeFalse();
    });

    test('it is off the marketing page, the add-ons list and every tier', function () {
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('features', fn ($features) => ! collect($features)->contains('key', 'ai-assist')));

        $shop = tierShop();
        $owner = actingAsShopMember($shop->fresh(), 'Owner');
        $this->actingAs($owner)->get(route('shop.addons.index'))->assertInertia(fn ($page) => $page
            ->where('addons', fn ($addons) => ! collect($addons)->contains('feature', 'ai-assist')));
        $this->actingAs($owner)->post(route('shop.addons.store', Feature::AiAssist->value))->assertNotFound();

        $plan = Plan::offeredByKey('start-up');
        $this->actingAs(actingAsPlatformAdmin())->get(route('platform-admin.plans.index'))->assertInertia(fn ($page) => $page
            ->where('allFeatures', fn ($all) => collect($all)->firstWhere('key', 'ai-assist')['discontinued'] === true));

        editTier('start-up', ['monthly_price_cents' => $plan->monthly_price_cents, 'features' => [Feature::Customers->value, Feature::AiAssist->value]]);
        expect(Plan::offeredByKey('start-up')->hasFeature(Feature::AiAssist))->toBeFalse();
    });

    test('the owner is told once, add-on billing stops, and the notice says what was lost', function () {
        Notification::fake();
        $affected = tierShop();
        $withAddon = tierShop('solo', attributes: ['extra_features' => [Feature::AiAssist->value, Feature::JobBoard->value]]);
        $subscription = ShopFeatureSubscription::query()->forceCreate(['shop_id' => $withAddon->id, 'feature' => Feature::AiAssist->value, 'started_at' => now()]);
        $unaffected = tierShop('solo');

        $this->artisan('app:announce-discontinued-features')->assertSuccessful();

        Notification::assertSentTo([$affected->owner, $withAddon->owner], PlanUpdated::class, fn (PlanUpdated $n) => $n->changes['retired'] === [Feature::AiAssist->value] && $n->changes['price'] === null);
        Notification::assertNotSentTo($unaffected->owner, PlanUpdated::class);
        Notification::assertCount(2);

        expect($subscription->fresh()->cancelled_at)->not->toBeNull()
            ->and($withAddon->fresh()->extra_features)->toBe([Feature::JobBoard->value])
            ->and($withAddon->fresh()->hasFeature(Feature::JobBoard))->toBeTrue()
            ->and($affected->fresh()->plan_notice['retired'])->toBe([Feature::AiAssist->value])
            ->and($affected->fresh()->notified_discontinued_features)->toBe([Feature::AiAssist->value]);

        // Running it again is harmless.
        $this->artisan('app:announce-discontinued-features')->assertSuccessful();
        Notification::assertCount(2);
    });

    test('the email says it is gone from every plan and that add-on billing has stopped', function () {
        $shop = tierShop();
        $mail = (new PlanUpdated($shop, ['plan_name' => 'Shop', 'added' => [], 'retired' => [Feature::AiAssist->value], 'price' => null, 'seats' => null]))->toMail($shop->owner);
        $text = implode("\n", $mail->introLines);

        expect($mail->subject)->toBe('A feature is no longer available')
            ->and($text)->toContain('No longer available')->toContain(Feature::AiAssist->label())
            ->and($text)->toContain('can no longer support')->toContain('removed from every plan')->toContain('will not be billed')
            ->and($text)->toContain('keep every other feature');
    });

    test('the Plan page lists what was retired', function () {
        $shop = tierShop();
        $this->artisan('app:announce-discontinued-features');
        $owner = actingAsShopMember($shop->fresh(), 'Owner');

        $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
            ->where('planNotice.retired.0.label', Feature::AiAssist->label())
            ->where('currentPlan.features', fn ($features) => ! collect($features)->contains(Feature::AiAssist->label())));
    });

    test('a feature added and retired in the same notice is only shown as retired', function () {
        $shop = tierShop();
        $shop->announcePlanChange(['plan_name' => 'Shop', 'added' => [Feature::AiAssist->value, Feature::MaterialTracking->value]]);
        $shop->announcePlanChange(['plan_name' => 'Shop', 'retired' => [Feature::AiAssist->value]]);

        expect($shop->fresh()->plan_notice['added'])->toBe([Feature::MaterialTracking->value])
            ->and($shop->fresh()->plan_notice['retired'])->toBe([Feature::AiAssist->value]);
    });
});
