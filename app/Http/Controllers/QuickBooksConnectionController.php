<?php

namespace App\Http\Controllers;

use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class QuickBooksConnectionController extends Controller
{
    public function show(): Response
    {
        $connection = Auth::user()->currentShop->quickBooksConnection;

        return Inertia::render('QuickBooks/Show', [
            'connection' => $connection ? [
                'realm_id' => $connection->realm_id,
                'connected_at' => $connection->connected_at->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * Kicks off the OAuth handshake - the state value is a CSRF guard
     * checked again in callback() so a forged redirect can't link a
     * connection to the wrong shop.
     */
    public function connect(Request $request, QuickBooksClient $client): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('quickbooks_oauth_state', $state);

        return redirect()->away($client->authorizationUrl($state));
    }

    public function callback(Request $request, QuickBooksClient $client): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
            'realmId' => ['required', 'string'],
        ]);

        $expectedState = $request->session()->pull('quickbooks_oauth_state');

        abort_unless($expectedState && hash_equals($expectedState, $validated['state']), 403);

        $tokens = $client->exchangeCodeForTokens($validated['code']);

        $shop = Auth::user()->currentShop;

        // shop_id isn't fillable (same reasoning as every other tenant-owned
        // model) - going through the relation sets it via direct property
        // assignment instead of mass assignment, and its own where clause
        // already scopes the "does one exist" check to this shop.
        $shop->quickBooksConnection()->updateOrCreate([], [
            'realm_id' => $validated['realmId'],
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'refresh_token_expires_at' => now()->addSeconds($tokens['x_refresh_token_expires_in']),
            'connected_at' => now(),
        ]);

        return redirect()->route('quickbooks.show')->with('status', 'QuickBooks connected.');
    }

    public function destroy(QuickBooksClient $client): RedirectResponse
    {
        $connection = Auth::user()->currentShop->quickBooksConnection;

        abort_unless($connection, 404);

        $client->revoke($connection);
        $connection->delete();

        return redirect()->route('quickbooks.show')->with('status', 'QuickBooks disconnected.');
    }
}
