<?php

use App\Models\Invitation;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\ShopInvitationSent;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

test('the owner can invite someone by email', function () {
    Notification::fake();
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->post(route('team.invite'), [
        'email' => 'new@example.com', 'role' => 'Staff',
    ])->assertRedirect(route('team.index'));

    $invitation = Invitation::sole();
    expect($invitation->shop_id)->toBe($shop->id)
        ->and($invitation->email)->toBe('new@example.com')
        ->and($invitation->role)->toBe('Staff')
        ->and($invitation->invited_by)->toBe($owner->id)
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentOnDemand(
        ShopInvitationSent::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@example.com',
    );
});

test('a non-owner cannot invite anyone', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');

    $this->actingAs($admin)->post(route('team.invite'), [
        'email' => 'new@example.com', 'role' => 'Staff',
    ])->assertForbidden();

    expect(Invitation::count())->toBe(0);
});

test('inviting cannot grant the Owner role', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->post(route('team.invite'), [
        'email' => 'new@example.com', 'role' => 'Owner',
    ])->assertSessionHasErrors('role');
});

test('re-inviting the same address refreshes one row instead of duplicating it', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->post(route('team.invite'), ['email' => 'new@example.com', 'role' => 'Staff']);
    $firstToken = Invitation::sole()->token;

    $this->actingAs($owner)->post(route('team.invite'), ['email' => 'New@Example.com', 'role' => 'Admin']);

    $invitation = Invitation::sole();
    expect($invitation->token)->not->toBe($firstToken)
        ->and($invitation->role)->toBe('Admin')
        ->and($invitation->email)->toBe('new@example.com');
});

test('the team page only shows pending invitations and the invite form to the owner', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $admin = actingAsShopMember($shop, 'Admin');
    Invitation::create([
        'shop_id' => $shop->id, 'email' => 'pending@example.com', 'role' => 'Staff',
        'token' => 'tok', 'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($owner)->get(route('team.index'))
        ->assertInertia(fn ($page) => $page->where('canInvite', true)->has('pendingInvitations', 1));

    $this->actingAs($admin)->get(route('team.index'))
        ->assertInertia(fn ($page) => $page->where('canInvite', false)->has('pendingInvitations', 0));
});

test('visiting an unknown invitation token 404s', function () {
    $this->get(route('invitations.show', 'does-not-exist'))->assertNotFound();
});

test('a pending invite for a brand new email offers account creation', function () {
    $shop = Shop::factory()->create(['name' => 'Ironclad']);
    $invitation = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'brand-new@example.com', 'role' => 'Staff',
        'token' => 'tok-1', 'expires_at' => now()->addDays(7),
    ]);

    $this->get(route('invitations.show', $invitation->token))
        ->assertInertia(fn ($page) => $page
            ->component('Invitations/Accept')
            ->where('mode', 'create-account')
            ->where('shopName', 'Ironclad')
            ->where('role', 'Staff'));
});

test('a pending invite for an existing account requires logging in', function () {
    $shop = Shop::factory()->create();
    $existing = User::factory()->create(['email' => 'existing@example.com']);
    $invitation = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'existing@example.com', 'role' => 'Admin',
        'token' => 'tok-2', 'expires_at' => now()->addDays(7),
    ]);

    $this->get(route('invitations.show', $invitation->token))
        ->assertInertia(fn ($page) => $page->where('mode', 'login-required'));
});

test('a pending invite already signed in as the invitee is ready to accept', function () {
    $shop = Shop::factory()->create();
    $existing = User::factory()->create(['email' => 'me@example.com']);
    $invitation = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'me@example.com', 'role' => 'Admin',
        'token' => 'tok-3', 'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($existing)->get(route('invitations.show', $invitation->token))
        ->assertInertia(fn ($page) => $page->where('mode', 'ready'));
});

test('an accepted invite shows as already accepted, an expired one shows as expired', function () {
    $shop = Shop::factory()->create();
    $accepted = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'a@example.com', 'role' => 'Staff',
        'token' => 'tok-4', 'expires_at' => now()->addDays(7), 'accepted_at' => now(),
    ]);
    $expired = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'b@example.com', 'role' => 'Staff',
        'token' => 'tok-5', 'expires_at' => now()->subDay(),
    ]);

    $this->get(route('invitations.show', $accepted->token))
        ->assertInertia(fn ($page) => $page->where('mode', 'accepted'));
    $this->get(route('invitations.show', $expired->token))
        ->assertInertia(fn ($page) => $page->where('mode', 'expired'));
});

test('accepting as a brand new email creates the account, verified, and joins the shop', function () {
    $shop = Shop::factory()->create(['name' => 'Ironclad']);
    $invitation = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'brand-new@example.com', 'role' => 'Staff',
        'token' => 'tok-6', 'expires_at' => now()->addDays(7),
    ]);

    $this->post(route('invitations.accept', $invitation->token), [
        'name' => 'Brand New', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    $user = User::where('email', 'brand-new@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->current_shop_id)->toBe($shop->id)
        ->and($shop->members()->where('users.id', $user->id)->exists())->toBeTrue()
        ->and($invitation->fresh()->isAccepted())->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
    expect($user->fresh()->hasRole('Staff'))->toBeTrue();
});

test('accepting for an existing account requires the correct password', function () {
    $shop = Shop::factory()->create();
    $existing = User::factory()->create(['email' => 'existing@example.com']);
    $invitation = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'existing@example.com', 'role' => 'Admin',
        'token' => 'tok-7', 'expires_at' => now()->addDays(7),
    ]);

    $this->post(route('invitations.accept', $invitation->token), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');
    $this->assertGuest();

    $this->post(route('invitations.accept', $invitation->token), ['password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($existing);
    expect($shop->members()->where('users.id', $existing->id)->exists())->toBeTrue();
});

test('accepting while already signed in as the invitee needs no password', function () {
    $shop = Shop::factory()->create();
    $existing = User::factory()->create(['email' => 'me@example.com']);
    $invitation = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'me@example.com', 'role' => 'Staff',
        'token' => 'tok-8', 'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($existing)->post(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('dashboard'));

    expect($shop->members()->where('users.id', $existing->id)->exists())->toBeTrue();
});

test('an expired or already-accepted invitation cannot be accepted', function () {
    $shop = Shop::factory()->create();
    $expired = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'a@example.com', 'role' => 'Staff',
        'token' => 'tok-9', 'expires_at' => now()->subDay(),
    ]);

    $this->post(route('invitations.accept', $expired->token), [
        'name' => 'X', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'a@example.com')->exists())->toBeFalse();
});

test('accepting an invite to shop B does not grant access to any other shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $invitation = Invitation::create([
        'shop_id' => $shopB->id, 'email' => 'brand-new@example.com', 'role' => 'Staff',
        'token' => 'tok-10', 'expires_at' => now()->addDays(7),
    ]);

    $this->post(route('invitations.accept', $invitation->token), [
        'name' => 'X', 'password' => 'password', 'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'brand-new@example.com')->sole();

    expect($shopA->members()->where('users.id', $user->id)->exists())->toBeFalse()
        ->and($shopB->members()->where('users.id', $user->id)->exists())->toBeTrue();
});
