<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShopSuspendedController extends Controller
{
    /**
     * Landing spot for EnsureShopIsActive - a plain notice, not an error
     * page, since being suspended is a moderation state the shop's own
     * users should understand, not something they did wrong in the request.
     */
    public function show(): Response|RedirectResponse
    {
        $shop = Auth::user()->currentShop;

        if (! $shop?->isSuspended()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('ShopSuspended', [
            'shopName' => $shop->name,
        ]);
    }
}
