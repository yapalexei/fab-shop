<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'shop_name' => 'Test Shop',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = auth()->user();

    expect($user->currentShop->name)->toBe('Test Shop')
        ->and($user->ownedShops)->toHaveCount(1)
        ->and($user->shops)->toHaveCount(1)
        ->and($user->hasRole('Owner'))->toBeTrue();
});
