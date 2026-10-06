<?php

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register but have no shop yet - that comes from onboarding', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password', 'accepted_terms' => true,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = auth()->user();

    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->currentShop)->toBeNull()
        ->and($user->hasRole('Owner'))->toBeFalse();
});

test('registering sends a verification email', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password', 'accepted_terms' => true,
    ]);

    Notification::assertSentToTimes(auth()->user(), VerifyEmail::class, 1);
});

test('an unverified new user is sent to verify their email, not the dashboard', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password', 'accepted_terms' => true,
    ]);

    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});
