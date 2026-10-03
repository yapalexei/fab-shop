<?php

use App\Enums\Feature;
use App\Models\QuickBooksConnection;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    if (! Feature::QuickbooksSync->isShipped()) {
        $this->markTestSkipped('QuickBooks sync is switched off (future feature) - see Feature::isShipped().');
    }

    config([
        'services.quickbooks.client_id' => 'test-client-id',
        'services.quickbooks.client_secret' => 'test-client-secret',
        'services.quickbooks.redirect_uri' => 'http://localhost/quickbooks/callback',
    ]);
});

test('connect redirects to the intuit authorize url with a csrf state stored in session', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $response = $this->actingAs($user)->get(route('quickbooks.connect'));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->toStartWith('https://appcenter.intuit.com/connect/oauth2')
        ->and($location)->toContain('client_id=test-client-id')
        ->and($location)->toContain('scope=com.intuit.quickbooks.accounting');

    expect($this->app['session']->get('quickbooks_oauth_state'))->not->toBeNull();
});

test('callback exchanges the code for tokens and stores the connection', function () {
    Http::fake([
        'oauth.platform.intuit.com/*' => Http::response([
            'access_token' => 'access-123',
            'refresh_token' => 'refresh-123',
            'expires_in' => 3600,
            'x_refresh_token_expires_in' => 8726400,
        ]),
    ]);

    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('quickbooks.connect'));
    $state = $this->app['session']->get('quickbooks_oauth_state');

    $this->actingAs($user)->get(route('quickbooks.callback', [
        'code' => 'auth-code',
        'state' => $state,
        'realmId' => 'realm-1',
    ]))->assertRedirect(route('quickbooks.show'));

    $connection = QuickBooksConnection::sole();
    expect($connection->shop_id)->toBe($shop->id)
        ->and($connection->realm_id)->toBe('realm-1')
        ->and($connection->access_token)->toBe('access-123');
});

test('callback rejects a mismatched state', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)
        ->withSession(['quickbooks_oauth_state' => 'expected-state'])
        ->get(route('quickbooks.callback', ['code' => 'x', 'state' => 'forged-state', 'realmId' => 'r']))
        ->assertForbidden();

    expect(QuickBooksConnection::count())->toBe(0);
});

test('reconnecting replaces rather than duplicates the shop\'s connection', function () {
    Http::fake([
        'oauth.platform.intuit.com/*' => Http::response([
            'access_token' => 'access-new',
            'refresh_token' => 'refresh-new',
            'expires_in' => 3600,
            'x_refresh_token_expires_in' => 8726400,
        ]),
    ]);

    $shop = Shop::factory()->create();
    $shop->quickBooksConnection()->create([
        'realm_id' => 'realm-old', 'access_token' => 'access-old', 'refresh_token' => 'refresh-old',
        'expires_at' => now()->addHour(), 'refresh_token_expires_at' => now()->addDays(90), 'connected_at' => now()->subDay(),
    ]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->withSession(['quickbooks_oauth_state' => 's'])
        ->get(route('quickbooks.callback', ['code' => 'x', 'state' => 's', 'realmId' => 'realm-new']));

    expect(QuickBooksConnection::count())->toBe(1)
        ->and(QuickBooksConnection::sole()->realm_id)->toBe('realm-new');
});

test('a user only ever sees their own shop\'s connection status', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $shopB->quickBooksConnection()->create([
        'realm_id' => 'shop-b-realm', 'access_token' => 'a', 'refresh_token' => 'r',
        'expires_at' => now()->addHour(), 'refresh_token_expires_at' => now()->addDays(90), 'connected_at' => now(),
    ]);
    $userA = actingAsShopMember($shopA);

    $this->actingAs($userA)->get(route('quickbooks.show'))
        ->assertInertia(fn ($page) => $page->where('connection', null));
});

test('disconnect revokes and deletes the connection', function () {
    Http::fake(['developer.api.intuit.com/*' => Http::response([])]);

    $shop = Shop::factory()->create();
    $shop->quickBooksConnection()->create([
        'realm_id' => 'realm-1', 'access_token' => 'a', 'refresh_token' => 'r',
        'expires_at' => now()->addHour(), 'refresh_token_expires_at' => now()->addDays(90), 'connected_at' => now(),
    ]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->delete(route('quickbooks.destroy'))->assertRedirect();

    expect(QuickBooksConnection::count())->toBe(0);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'developer.api.intuit.com'));
});

test('disconnect still deletes the connection locally even if the revoke call fails', function () {
    Http::fake(['developer.api.intuit.com/*' => Http::response('Server error', 500)]);

    $shop = Shop::factory()->create();
    $shop->quickBooksConnection()->create([
        'realm_id' => 'realm-1', 'access_token' => 'a', 'refresh_token' => 'r',
        'expires_at' => now()->addHour(), 'refresh_token_expires_at' => now()->addDays(90), 'connected_at' => now(),
    ]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->delete(route('quickbooks.destroy'))->assertRedirect();

    expect(QuickBooksConnection::count())->toBe(0);
});

test('quickbooks routes are gated behind the quickbooks-sync feature', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => null]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('quickbooks.show'))->assertForbidden();
});
