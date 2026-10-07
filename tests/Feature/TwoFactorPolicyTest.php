<?php

use App\Actions\CreateShopAgent;
use App\Jobs\NotifyTwoFactorRequirement;
use App\Models\PlatformSetting;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\TwoFactorRequired;
use App\Support\TwoFactor;
use App\Support\TwoFactorPolicy;
use Illuminate\Support\Facades\Notification;

function setPlatformPolicy(array $roles, int $days = 14, $setAt = null): void
{
    PlatformSetting::current()->forceFill([
        'two_factor_required_roles' => $roles,
        'two_factor_grace_days' => $days,
        'two_factor_policy_set_at' => $setAt ?? now(),
    ])->save();
}

/** Pretend the person joined the shop some days ago (a new member starts "now"). */
function joinedDaysAgo(User $user, Shop $shop, int $days): void
{
    DB::table('shop_user')->where('shop_id', $shop->id)->where('user_id', $user->id)
        ->update(['created_at' => now()->subDays($days)]);
}

function enableTwoFactorFor(User $user): void
{
    $user->forceFill(['two_factor_secret' => TwoFactor::newSecret(), 'two_factor_confirmed_at' => now()])->save();
}

test('a platform admin can set the default requirement and covered people are emailed', function () {
    Notification::fake();
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $staff = actingAsShopMember($shop, 'Staff');
    $admin = actingAsPlatformAdmin();

    $this->actingAs($admin)->put(route('platform-admin.security.two-factor'), ['roles' => ['Owner'], 'grace_days' => 10])
        ->assertSessionHasNoErrors();

    expect(TwoFactorPolicy::platform())->toMatchArray(['roles' => ['Owner'], 'grace_days' => 10]);

    Notification::assertSentTo($owner, TwoFactorRequired::class);
    Notification::assertNotSentTo($staff, TwoFactorRequired::class);
});

test('only a platform admin can change the platform default', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->put(route('platform-admin.security.two-factor'), ['roles' => ['Owner'], 'grace_days' => 10])
        ->assertForbidden();
    $this->actingAs($owner)->get(route('platform-admin.security.index'))->assertForbidden();
});

test('saving the same policy again does not restart the countdown or re-notify', function () {
    Notification::fake();
    $admin = actingAsPlatformAdmin();
    setPlatformPolicy(['Owner'], 14, now()->subDays(5));

    $this->actingAs($admin)->put(route('platform-admin.security.two-factor'), ['roles' => ['Owner'], 'grace_days' => 14]);

    expect(TwoFactorPolicy::platform()['set_at']->diffInDays(now()))->toBeGreaterThanOrEqual(4);
    Notification::assertNothingSent();
});

test('the deadline is the grace period from the policy change', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);
    joinedDaysAgo($owner, $shop, 30);
    setPlatformPolicy(['Owner'], 10, now()->subDays(3));

    $req = TwoFactorPolicy::requirementFor($owner, $shop);

    expect($req['overdue'])->toBeFalse()
        ->and($req['days_left'])->toBe(7);
});

test('someone who joins later gets the full grace period', function () {
    $shop = Shop::factory()->create();
    setPlatformPolicy(['Staff'], 14, now()->subDays(60));
    $staff = actingAsShopMember($shop, 'Staff');

    $req = TwoFactorPolicy::requirementFor($staff, $shop);

    expect($req['overdue'])->toBeFalse()
        ->and($req['days_left'])->toBe(14);
});

test('a role that is not covered, or a person who already has it on, owes nothing', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $owner = actingAsShopMember($shop, 'Owner');
    setPlatformPolicy(['Owner']);

    expect(TwoFactorPolicy::requirementFor($staff, $shop))->toBeNull();

    enableTwoFactorFor($owner);
    expect(TwoFactorPolicy::requirementFor($owner->fresh(), $shop))->toBeNull();
});

test('a shop can turn the platform requirement off for itself', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);
    setPlatformPolicy(['Owner']);
    expect(TwoFactorPolicy::requirementFor($owner, $shop))->not->toBeNull();

    $this->actingAs($owner)->put(route('shop.security.two-factor'), ['mode' => 'custom', 'roles' => [], 'grace_days' => 14])
        ->assertSessionHasNoErrors();

    expect(TwoFactorPolicy::requirementFor($owner->fresh(), $shop->fresh()))->toBeNull();

    $this->actingAs($owner)->put(route('shop.security.two-factor'), ['mode' => 'inherit']);
    expect(TwoFactorPolicy::requirementFor($owner->fresh(), $shop->fresh()))->not->toBeNull();
});

test('a shop can require more than the platform default, and is emailed about it', function () {
    Notification::fake();
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($owner)->put(route('shop.security.two-factor'), ['mode' => 'custom', 'roles' => ['Owner', 'Staff'], 'grace_days' => 7]);

    Notification::assertSentTo([$owner, $staff], TwoFactorRequired::class);
    expect(TwoFactorPolicy::forShop($shop->fresh())['source'])->toBe('shop');
});

test('only the owner can change the shop requirement', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');

    $this->actingAs($admin)->put(route('shop.security.two-factor'), ['mode' => 'custom', 'roles' => ['Staff'], 'grace_days' => 7])
        ->assertForbidden();
});

test("one shop's requirement does not affect another shop's people", function () {
    $a = Shop::factory()->create();
    $b = Shop::factory()->create();
    $ownerA = actingAsShopMember($a);
    $ownerB = actingAsShopMember($b);

    $this->actingAs($ownerA)->put(route('shop.security.two-factor'), ['mode' => 'custom', 'roles' => ['Owner'], 'grace_days' => 7]);

    expect(TwoFactorPolicy::requirementFor($ownerB->fresh(), $b->fresh()))->toBeNull();
});

test('before the deadline the app works and shows a reminder', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);
    setPlatformPolicy(['Owner'], 14);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('twoFactorNotice.overdue', false)->where('twoFactorNotice.days_left', 14));
});

test('after the deadline the person is held on the profile page until they set it up', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);
    joinedDaysAgo($owner, $shop, 30);
    setPlatformPolicy(['Owner'], 7, now()->subDays(8));

    $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('profile.edit'));
    $this->actingAs($owner)->get(route('profile.edit'))->assertOk();
    $this->actingAs($owner)->post(route('two-factor.enable'))->assertRedirect();
    $this->actingAs($owner)->post(route('logout'))->assertRedirect('/');

    $owner->refresh();
    enableTwoFactorFor($owner);
    $this->actingAs($owner->fresh())->get(route('dashboard'))->assertOk();
});

test('the shop switcher stays reachable while overdue', function () {
    $a = Shop::factory()->create();
    $b = Shop::factory()->create();
    $user = actingAsShopMember($a, 'Owner');
    $b->members()->attach($user);
    joinedDaysAgo($user, $a, 30);
    setPlatformPolicy(['Owner'], 7, now()->subDays(8));

    $this->actingAs($user)->post(route('current-shop.update', $b))->assertRedirect();
});

test('AI agents are never asked for two-factor', function () {
    $shop = Shop::factory()->create();
    $agent = (new CreateShopAgent)($shop);
    setPlatformPolicy(['Owner', 'Admin', 'Staff'], 1, now()->subDays(10));

    expect(TwoFactorPolicy::requirementFor($agent, $shop))->toBeNull()
        ->and(TwoFactorPolicy::affectedMembers($shop))->toBe([]);
});

test('a platform-wide notice skips shops that have their own requirement', function () {
    Notification::fake();
    $following = Shop::factory()->create();
    $own = Shop::factory()->create(['two_factor_required_roles' => []]);
    $u1 = actingAsShopMember($following);
    $u2 = actingAsShopMember($own);
    setPlatformPolicy(['Owner']);

    (new NotifyTwoFactorRequirement(null))->handle();

    Notification::assertSentTo($u1, TwoFactorRequired::class);
    Notification::assertNotSentTo($u2, TwoFactorRequired::class);
});
