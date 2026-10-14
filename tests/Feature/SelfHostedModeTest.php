<?php

use App\Enums\Feature;
use App\Models\Invitation;
use App\Models\Shop;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

function selfHosted(): void
{
    config(['app.mode' => Edition::SELF_HOSTED]);
}

/** A self-hosted shop as the installer leaves it: no plan, no trial. */
function selfHostedShop(): Shop
{
    selfHosted();

    return Shop::factory()->create(['plan_id' => null, 'trial_started_at' => null, 'trial_ends_at' => null]);
}

$setup = [
    'name' => 'Pat Welder',
    'email' => 'Pat@Example.com',
    'password' => 'a-long-password-1',
    'password_confirmation' => 'a-long-password-1',
    'shop_name' => 'Pat Welding',
    'timezone' => 'America/Denver',
];

// --- Edition ---------------------------------------------------------------

test('the cloud edition is the default', function () {
    expect(Edition::selfHosted())->toBeFalse()
        ->and(Edition::cloud())->toBeTrue();
});

// --- Entitlements ----------------------------------------------------------

test('a self-hosted shop has every shipped feature with no plan or trial', function () {
    $shop = selfHostedShop();

    foreach (Feature::cases() as $feature) {
        expect($shop->hasFeature($feature))->toBe($feature->isShipped());
        expect($shop->canViewFeature($feature))->toBe($feature->isShipped());
    }

    expect($shop->enabledFeatures()->all())->toEqualCanonicalizing(
        collect(Feature::cases())->filter->isShipped()->values()->all(),
    )
        ->and($shop->readOnlyFeatures())->toBeEmpty()
        ->and($shop->canUpgrade())->toBeFalse();
});

test('the same shop without a plan has nothing in the cloud edition', function () {
    $shop = Shop::factory()->create(['plan_id' => null, 'trial_ends_at' => null]);

    expect($shop->hasFeature(Feature::QuoteBuilder))->toBeFalse();
});

test('a discontinued feature stays off even when self-hosted', function () {
    $shop = selfHostedShop();
    config(['shop.discontinued_features' => [Feature::AiAssist->value]]);

    expect($shop->hasFeature(Feature::AiAssist))->toBeFalse()
        ->and($shop->enabledFeatures())->not->toContain(Feature::AiAssist);
});

test('seats are unlimited when self-hosted', function () {
    $shop = selfHostedShop();
    $shop->forceFill(['seat_limit_override' => 1])->save();

    expect($shop->seatLimit())->toBeNull()
        ->and($shop->seatCapacity())->toBeNull()
        ->and($shop->hasRoomForSeats(50))->toBeTrue();
});

test('feature-gated pages open for a self-hosted shop with no plan', function () {
    $user = actingAsShopMember(selfHostedShop());

    $this->actingAs($user)->get(route('quotes.index'))->assertOk();
    $this->actingAs($user)->get(route('jobs.index'))->assertOk();
    $this->actingAs($user)->get(route('procedures.index'))->assertOk();
});

test('the dashboard has no plan, trial or upgrade prompts', function () {
    $user = actingAsShopMember(selfHostedShop());

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selfHosted', true)
            ->where('shop.on_trial', false)
            ->where('shop.can_upgrade', false)
            ->where('shop.plan_name', null)
            ->where('shop.show_trial_ended_notice', false)
            ->where('readOnlyFeatures', []));
});

// --- Cloud-only surface is gone -------------------------------------------

test('cloud-only pages are 404 when self-hosted', function () {
    $shop = selfHostedShop();
    $owner = actingAsShopMember($shop);
    $admin = User::factory()->create(['current_shop_id' => $shop->id]);
    $admin->forceFill(['is_platform_admin' => true])->save();

    foreach (['legal.terms', 'legal.privacy', 'legal.sms', 'shop.plan.index', 'shop.addons.index'] as $name) {
        $this->actingAs($owner)->get(route($name))->assertNotFound();
    }

    $this->actingAs($owner)->post(route('shop.plan.update'), [])->assertNotFound();
    $this->actingAs($owner)->put(route('shop.seats.update'), [])->assertNotFound();
    $this->actingAs($owner)->post(route('shop.data.deletion'), [])->assertNotFound();
    $this->actingAs($owner)->post(route('trial-ended-notice.dismiss'))->assertNotFound();

    // Even an account flagged as platform admin gets no console.
    $this->actingAs($admin)->get(route('platform-admin.index'))->assertNotFound();
    $this->actingAs($admin)->get(route('platform-admin.plans.index'))->assertNotFound();
});

test('public sign-up is closed when self-hosted', function () {
    selfHosted();

    $this->get(route('register'))->assertNotFound();
    $this->post(route('register'), [
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1', 'accepted_terms' => '1',
    ])->assertNotFound();

    expect(User::count())->toBe(0);
});

test('the cloud-only pages still work in the cloud edition', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    $this->get(route('register'))->assertOk();
    $this->get(route('legal.terms'))->assertOk();
    $this->actingAs($user)->get(route('shop.plan.index'))->assertOk();
});

test('robots.txt tells crawlers to stay out of a self-hosted install, even in production', function () {
    selfHosted();
    app()->detectEnvironment(fn () => 'production');

    expect($this->get(route('seo.robots'))->getContent())->toContain('Disallow: /')->not->toContain('Sitemap:');
});

test('the cloud-only scheduled jobs do not run when self-hosted', function () {
    selfHosted();

    $due = collect(app(Schedule::class)->dueEvents(app()))
        ->map(fn ($event) => $event->command)
        ->filter(fn ($command) => str_contains((string) $command, 'app:notify-expired-trials')
            || str_contains((string) $command, 'app:apply-scheduled-plan-changes')
            || str_contains((string) $command, 'app:announce-discontinued-features')
            || str_contains((string) $command, 'app:purge-deleted-shops'));

    expect($due)->toBeEmpty();
});

// --- Landing and first-run -------------------------------------------------

test('the root goes to setup on a fresh install and to the app after that', function () {
    selfHosted();

    $this->get('/')->assertRedirect(route('setup'));

    User::factory()->create();

    $this->get('/')->assertRedirect(route('dashboard'));
});

test('sign-in sends a fresh install to setup', function () {
    selfHosted();

    $this->get(route('login'))->assertRedirect(route('setup'));

    User::factory()->create();

    $this->get(route('login'))->assertOk();
});

test('the setup page does not exist in the cloud edition', function () {
    $this->get('/setup')->assertNotFound();
    $this->post('/setup', [])->assertNotFound();
});

test('setup creates the owner and the shop, signs them in and starts the wizard', function () use ($setup) {
    selfHosted();

    $this->get(route('setup'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Setup/Index'));

    $this->post(route('setup.store'), $setup)->assertRedirect(route('dashboard'));

    $user = User::firstOrFail();
    $shop = $user->currentShop;

    $this->assertAuthenticatedAs($user);

    expect($user->email)->toBe('pat@example.com')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($shop->name)->toBe('Pat Welding')
        ->and($shop->timezone)->toBe('America/Denver')
        ->and($shop->owner_id)->toBe($user->id)
        ->and($shop->plan_id)->toBeNull()
        ->and($shop->trial_ends_at)->toBeNull()
        ->and($shop->onboarding_step)->toBe('team')
        ->and($shop->isOnboarded())->toBeFalse()
        ->and($shop->members()->count())->toBe(1);

    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
    expect($user->hasRole('Owner'))->toBeTrue();

    // Drops straight into the short wizard, then the app.
    $this->get(route('dashboard'))->assertRedirect(route('onboarding.show'));
    $this->get(route('onboarding.show'))
        ->assertInertia(fn ($page) => $page->component('Onboarding/TeamStep'));
});

test('setup closes for good once an account exists', function () use ($setup) {
    selfHosted();
    User::factory()->create();

    $this->get(route('setup'))->assertRedirect(route('login'));
    $this->post(route('setup.store'), $setup)->assertRedirect(route('login'));

    expect(User::count())->toBe(1)->and(Shop::count())->toBe(0);
});

test('a configured setup key hides the setup page from anyone without it', function () use ($setup) {
    selfHosted();
    config(['shop.selfhosted_setup_key' => 'open-sesame']);

    $this->get(route('setup'))->assertNotFound();
    $this->get(route('setup', ['key' => 'wrong']))->assertNotFound();
    $this->post(route('setup.store'), $setup)->assertNotFound();
    expect(User::count())->toBe(0);

    $this->get(route('setup', ['key' => 'open-sesame']))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Setup/Index')->where('setupKey', 'open-sesame'));

    $this->post(route('setup.store'), [...$setup, 'key' => 'open-sesame'])->assertRedirect(route('dashboard'));
    expect(User::count())->toBe(1);
});

test('setup validates the form', function () use ($setup) {
    selfHosted();

    $this->post(route('setup.store'), [...$setup, 'password_confirmation' => 'different'])
        ->assertSessionHasErrors('password');
    $this->post(route('setup.store'), [...$setup, 'shop_name' => ''])
        ->assertSessionHasErrors('shop_name');
    $this->post(route('setup.store'), [...$setup, 'timezone' => 'Mars/Olympus'])
        ->assertSessionHasErrors('timezone');

    expect(User::count())->toBe(0);
});

test('setup is recorded in the security log', function () use ($setup) {
    selfHosted();

    $this->post(route('setup.store'), $setup);

    expect(Activity::query()
        ->where('log_name', 'security')
        ->where('description', 'self-hosted install completed')
        ->exists())->toBeTrue();
});

test('the browser may open a websocket to its own origin when self-hosted', function () {
    selfHosted();
    User::factory()->create();

    // Same-origin live updates: Caddy forwards /app/* to Reverb, so there is
    // no configured Reverb host - the policy must name this site's own.
    config(['security.reverb.host' => null]);

    $csp = $this->get(route('login'))->headers->get('Content-Security-Policy');

    expect($csp)->toContain('connect-src')->toContain('ws://localhost');
});

// --- selfhosted:install ----------------------------------------------------

test('selfhosted:install creates the owner and shop from options', function () {
    selfHosted();

    $this->artisan('selfhosted:install', [
        '--name' => 'Pat Welder', '--email' => 'pat@example.com', '--password' => 'a-long-password-1',
        '--shop' => 'Pat Welding', '--timezone' => 'America/Denver',
    ])->assertExitCode(0);

    $user = User::firstOrFail();

    expect($user->currentShop->name)->toBe('Pat Welding')
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('selfhosted:install refuses outside a self-hosted install and once set up', function () {
    $options = [
        '--name' => 'Pat', '--email' => 'pat@example.com', '--password' => 'a-long-password-1', '--shop' => 'Pat Welding',
    ];

    $this->artisan('selfhosted:install', $options)->assertExitCode(1);
    expect(User::count())->toBe(0);

    selfHosted();
    User::factory()->create();

    $this->artisan('selfhosted:install', $options)->assertExitCode(1);
    expect(User::count())->toBe(1);
});

test('selfhosted:install rejects bad answers', function () {
    selfHosted();

    $this->artisan('selfhosted:install', [
        '--name' => 'Pat', '--email' => 'not-an-email', '--password' => 'short', '--shop' => 'Pat Welding',
    ])->assertExitCode(1);

    expect(User::count())->toBe(0);
});

// --- Invitations still work -------------------------------------------------

test('the owner can invite people with no seat limit', function () {
    $shop = selfHostedShop();
    $owner = actingAsShopMember($shop);
    Notification::fake();

    foreach (range(1, 12) as $i) {
        $this->actingAs($owner)->post(route('team.invite'), ['email' => "person{$i}@example.com", 'role' => 'Staff'])
            ->assertSessionHasNoErrors();
    }

    expect(Invitation::count())->toBe(12);
});
