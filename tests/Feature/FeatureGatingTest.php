<?php

use App\Enums\Feature;
use App\Models\Customer;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;

test('a shop on trial has every feature regardless of plan', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->addDays(14), 'plan_id' => null]);

    foreach (Feature::cases() as $feature) {
        expect($shop->hasFeature($feature))->toBeTrue();
    }
});

test('a shop past trial with no plan has no features', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => null]);

    expect($shop->hasFeature(Feature::Customers))->toBeFalse();
});

test('a shop past trial only has its plan\'s features', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::Customers, Feature::QuoteBuilder]);

    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    expect($shop->hasFeature(Feature::Customers))->toBeTrue()
        ->and($shop->hasFeature(Feature::QuoteBuilder))->toBeTrue()
        ->and($shop->hasFeature(Feature::JobBoard))->toBeFalse();
});

test('a locked feature route is read-only once the trial has ended and the plan lacks it', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);

    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('customers.index'))->assertOk();
    $this->actingAs($user)->post(route('customers.store'), [])->assertForbidden();
});

test('enabledFeatures is shared with the frontend and reflects trial access', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->addDays(14), 'plan_id' => null]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('customers.index'))
        ->assertInertia(fn ($page) => $page->where('enabledFeatures', fn ($features) => collect($features)->contains('customers')));
});

test('an active add-on subscription grants a feature the plan does not include', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::Customers]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    expect($shop->hasFeature(Feature::MaterialTracking))->toBeFalse();

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $shop->id,
        'feature' => Feature::MaterialTracking->value,
        'started_at' => now(),
    ])->save();

    expect($shop->hasFeature(Feature::MaterialTracking))->toBeTrue()
        ->and($shop->enabledFeatures())->toContain(Feature::MaterialTracking);
});

test('a cancelled add-on subscription no longer grants the feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $shop->id,
        'feature' => Feature::QualificationRecords->value,
        'started_at' => now()->subMonth(),
        'cancelled_at' => now(),
    ])->save();

    expect($shop->hasFeature(Feature::QualificationRecords))->toBeFalse();
});

test('an add-on subscription for an already-bundled feature does not duplicate it in enabledFeatures', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::Customers]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $shop->id,
        'feature' => Feature::Customers->value,
        'started_at' => now(),
    ])->save();

    expect($shop->enabledFeatures()->filter(fn (Feature $f) => $f === Feature::Customers))->toHaveCount(1);
});

test('hasFeature checks the shop passed in, not the authenticated user\'s current shop', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);

    $otherShop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $targetShop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);

    (new ShopFeatureSubscription)->forceFill([
        'shop_id' => $targetShop->id,
        'feature' => Feature::MaterialTracking->value,
        'started_at' => now(),
    ])->save();

    // Authenticated user's *current* shop is $otherShop, not $targetShop -
    // the BelongsToShop global scope would wrongly filter by $otherShop's
    // id if Shop::hasActiveFeatureSubscription() didn't bypass it.
    $user = actingAsShopMember($otherShop);
    $this->actingAs($user);

    expect($targetShop->hasFeature(Feature::MaterialTracking))->toBeTrue();
});

test('a feature with a longer admin-set trial stays free after the shop\'s default trial ends', function () {
    PlatformSetting::current()->update(['default_trial_days' => 14]);
    PlatformSetting::forgetCache();
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->update(['trial_days' => 30]);

    // trial_ends_at is 14 days from signup (the default) and has already
    // passed - but MaterialTracking's own trial runs to day 30, so it's
    // still within its own window 20 days after trial_ends_at.
    $shop = Shop::factory()->create([
        'trial_started_at' => now()->subDays(20),
        'trial_ends_at' => now()->subDays(6),
        'trial_baseline_days' => 14,
        'plan_id' => null,
    ]);

    expect($shop->hasFeature(Feature::MaterialTracking))->toBeTrue()
        ->and($shop->hasFeature(Feature::Customers))->toBeFalse();
});

test('a feature with a shorter admin-set trial locks before the shop\'s default trial ends', function () {
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::QualificationRecords->value)->update(['trial_days' => 3]);

    // QualificationRecords' own trial (3 days from signup) already lapsed,
    // even though the shop's overall 14-day trial_ends_at is still future.
    $shop = Shop::factory()->create([
        'trial_started_at' => now()->subDays(5),
        'trial_ends_at' => now()->addDays(9),
        'plan_id' => null,
    ]);

    expect($shop->hasFeature(Feature::QualificationRecords))->toBeFalse()
        ->and($shop->hasFeature(Feature::Customers))->toBeTrue();
});

test('enabledFeatures reflects a per-feature trial extension past the default trial window', function () {
    PlatformSetting::current()->update(['default_trial_days' => 14]);
    PlatformSetting::forgetCache();
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->update(['trial_days' => 30]);

    $shop = Shop::factory()->create([
        'trial_started_at' => now()->subDays(20),
        'trial_ends_at' => now()->subDays(6),
        'trial_baseline_days' => 14,
        'plan_id' => null,
    ]);

    expect($shop->enabledFeatures())->toContain(Feature::MaterialTracking)
        ->and($shop->enabledFeatures())->not->toContain(Feature::Customers);
});

test('a shop with no trial at all gets no feature from onTrialFor regardless of trial_days', function () {
    FeatureSetting::ensureDefaults();
    FeatureSetting::query()->where('feature', Feature::MaterialTracking->value)->update(['trial_days' => 365]);

    $shop = Shop::factory()->create(['trial_started_at' => null, 'trial_ends_at' => null, 'plan_id' => null]);

    expect($shop->hasFeature(Feature::MaterialTracking))->toBeFalse();
});

test('changing the platform default trial length does not retroactively change an existing shop\'s per-feature trial math', function () {
    // A shop created while the platform default was 14 days - default-length
    // features (Customers, at 14 days, matching that default) should stay
    // exactly aligned with the shop's own overall trial_ends_at.
    PlatformSetting::current()->update(['default_trial_days' => 14]);
    PlatformSetting::forgetCache();
    FeatureSetting::ensureDefaults();

    $shop = Shop::factory()->create([
        'trial_started_at' => now()->subDays(20),
        'trial_ends_at' => now()->subDays(6), // 14-day trial, already over
        'trial_baseline_days' => 14,
        'plan_id' => null,
    ]);

    expect($shop->hasFeature(Feature::Customers))->toBeFalse();

    // A platform admin later raises the platform-wide default to 30 days -
    // this must not resurrect this shop's already-ended default-length
    // trial by shifting the baseline the offset math uses for it.
    PlatformSetting::current()->update(['default_trial_days' => 30]);

    expect($shop->hasFeature(Feature::Customers))->toBeFalse();
});

test('a new shop\'s per-feature trial math uses its own baseline once the platform default has changed', function () {
    PlatformSetting::current()->update(['default_trial_days' => 30]);
    FeatureSetting::ensureDefaults(); // seeds every feature's trial_days to the current default (30)

    // Onboarding captures today's default (30) as this shop's own baseline.
    $shop = Shop::factory()->create([
        'trial_started_at' => now()->subDays(25),
        'trial_ends_at' => now()->addDays(5), // 30-day trial, 5 days left
        'trial_baseline_days' => 30,
        'plan_id' => null,
    ]);

    // Customers' trial_days (30) matches this shop's own baseline (30), so
    // the offset is zero - it should still be on trial along with the rest.
    expect($shop->hasFeature(Feature::Customers))->toBeTrue();
});

test('onboarding captures the platform\'s current default trial length as the new shop\'s own baseline', function () {
    $this->seed(PlanSeeder::class);
    PlatformSetting::current()->update(['default_trial_days' => 21]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.shop'), [
        'name' => 'X', 'plan' => 'free',
    ]);

    $shop = $user->fresh()->currentShop;

    expect($shop->trial_baseline_days)->toBe(21)
        ->and((int) $shop->trial_started_at->diffInDays($shop->trial_ends_at))->toBe(21);
});

test('a shop whose trial ended keeps view access but loses every write permission on locked resources', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $owner = actingAsShopMember($shop);
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);

    $permissions = $owner->fresh()->shopPermissions();

    expect($permissions)->toContain('customers.view')
        ->not->toContain('customers.update')
        ->not->toContain('customers.create')
        ->not->toContain('customers.delete')
        // resources with no feature stay fully usable
        ->toContain('shop-settings.update');

    $this->actingAs($owner)->get(route('customers.index'))->assertOk();
    $this->actingAs($owner)->get(route('customers.create'))->assertForbidden();
    $this->actingAs($owner)->get(route('customers.edit', $customer))->assertOk(); // renders read-only (view is enough)
    $this->actingAs($owner)->patch(route('customers.update', $customer), ['name' => 'X'])->assertForbidden();
    $this->actingAs($owner)->delete(route('customers.destroy', $customer))->assertForbidden();
});

test('read-only features and the upgrade flag are shared once the trial ended', function () {
    $this->seed(PlanSeeder::class);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => Plan::where('key', 'free')->value('id')]);
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('shop.can_upgrade', true)
        ->where('readOnlyFeatures', fn ($features) => collect($features)->contains('customers')));
});

test('the top tier sees no upgrade button and only the owner can upgrade', function () {
    $this->seed(PlanSeeder::class);
    $pro = Shop::factory()->create(['plan_id' => Plan::where('key', 'pro')->value('id')]);
    $owner = actingAsShopMember($pro);

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('shop.can_upgrade', false));

    $solo = Shop::factory()->create(['plan_id' => Plan::where('key', 'solo')->value('id')]);
    $admin = actingAsShopRole($solo, 'Admin');
    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('shop.can_upgrade', false));
    $this->actingAs($admin)->post(route('shop.plan.update'), ['plan' => 'pro'])->assertForbidden();
});

test('an owner can upgrade but not downgrade or stay put', function () {
    $this->seed(PlanSeeder::class);
    $shop = Shop::factory()->create(['plan_id' => Plan::where('key', 'start-up')->value('id')]);
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'solo'])->assertSessionHasErrors('plan');
    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up'])->assertSessionHasErrors('plan');
    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'pro'])->assertRedirect(route('dashboard'));

    expect($shop->fresh()->plan->key)->toBe('pro');
});
