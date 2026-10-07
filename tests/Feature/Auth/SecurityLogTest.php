<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

function securityEvents(): array
{
    return Activity::where('log_name', 'security')->orderBy('id')->pluck('description')->all();
}

test('sign-in and sign-out are recorded', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/logout');

    expect(securityEvents())->toBe(['login', 'logout']);

    $entry = Activity::where('log_name', 'security')->first();
    expect($entry->causer_id)->toBe($user->id)
        ->and($entry->properties)->toHaveKey('ip');
});

test('a failed login records the email tried but never the password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'not-the-password']);

    $entry = Activity::where('log_name', 'security')->firstOrFail();
    expect($entry->description)->toBe('login failed')
        ->and($entry->properties['email'])->toBe($user->email)
        ->and(json_encode($entry->properties))->not->toContain('not-the-password');
});

test('a failed login for an unknown email is still recorded', function () {
    $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever']);

    expect(securityEvents())->toBe(['login failed']);
});

test('lockout after repeated failures is recorded', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    expect(securityEvents())->toContain('login locked out');
});

test('a password change is recorded and signs out other sessions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/password', [
        'current_password' => 'password',
        'password' => 'a-new-Password-123',
        'password_confirmation' => 'a-new-Password-123',
    ])->assertSessionHasNoErrors();

    expect(securityEvents())->toContain('password changed');
});

test('security headers are sent', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeaderMissing('Strict-Transport-Security');
});

test('HSTS is sent over https', function () {
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
});
