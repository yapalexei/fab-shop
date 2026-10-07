<?php

use App\Models\Shop;
use App\Models\User;
use App\Support\TwoFactor;
use Spatie\Activitylog\Models\Activity;

function userWithTwoFactor(): User
{
    $user = actingAsShopMember(Shop::factory()->create());
    $user->forceFill([
        'two_factor_secret' => TwoFactor::newSecret(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user->fresh();
}

function currentCode(User $user): string
{
    return TwoFactor::google2fa()->getCurrentOtp($user->two_factor_secret);
}

test('setup needs a valid code before two-factor turns on', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($user)->post('/profile/two-factor');
    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasTwoFactorEnabled())->toBeFalse();

    $this->actingAs($user)->post('/profile/two-factor/confirm', ['code' => '000000'])
        ->assertSessionHasErrors('code');
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();

    $this->actingAs($user)->post('/profile/two-factor/confirm', ['code' => currentCode($user)])
        ->assertSessionHas('recovery_codes');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->fresh()->two_factor_recovery_codes)->toHaveCount(TwoFactor::RECOVERY_CODE_COUNT);
});

test('a password alone does not sign in a two-factor user', function () {
    $user = userWithTwoFactor();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('a valid code completes sign-in', function () {
    $user = userWithTwoFactor();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => currentCode($user)])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('a wrong code does not sign in and is logged', function () {
    $user = userWithTwoFactor();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(Activity::where('log_name', 'security')->where('description', 'two-factor failed')->count())->toBe(1);
});

test('the same code cannot be used twice', function () {
    $user = userWithTwoFactor();
    $code = currentCode($user);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => $code]);
    $this->assertAuthenticated();

    auth()->logout();
    $this->flushSession();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('a recovery code signs in once and is then spent', function () {
    $user = userWithTwoFactor();
    [$code] = TwoFactor::regenerateRecoveryCodes($user);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['recovery_code' => $code])->assertRedirect();
    $this->assertAuthenticated();
    expect($user->fresh()->two_factor_recovery_codes)->toHaveCount(TwoFactor::RECOVERY_CODE_COUNT - 1);

    auth()->logout();
    $this->flushSession();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['recovery_code' => $code])->assertSessionHasErrors('recovery_code');
    $this->assertGuest();
});

test('the challenge page needs a password step first', function () {
    $this->get('/two-factor-challenge')->assertRedirect(route('login'));
});

test('the challenge expires', function () {
    $user = userWithTwoFactor();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->travel(11)->minutes();

    $this->post('/two-factor-challenge', ['code' => currentCode($user)])->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the challenge locks out after repeated wrong codes', function () {
    $user = userWithTwoFactor();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    foreach (range(1, 5) as $i) {
        $this->post('/two-factor-challenge', ['code' => '000000']);
    }

    // Even the right code is refused while locked out.
    $this->post('/two-factor-challenge', ['code' => currentCode($user)])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('turning two-factor off needs the password', function () {
    $user = userWithTwoFactor();

    $this->actingAs($user)->delete('/profile/two-factor', ['password' => 'wrong'])
        ->assertSessionHasErrors('password');
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    $this->actingAs($user)->delete('/profile/two-factor', ['password' => 'password']);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->fresh()->two_factor_secret)->toBeNull();
});

test('the secret and recovery codes never reach the browser in shared props', function () {
    $user = userWithTwoFactor();
    TwoFactor::regenerateRecoveryCodes($user);

    expect($user->fresh()->toArray())->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes']);
});
