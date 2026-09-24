<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateShopSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShopSettingsController extends Controller
{
    public function edit(): Response
    {
        $shop = Auth::user()->currentShop;

        return Inertia::render('ShopSettings/Edit', [
            // Raw columns (null = using the default), not the resolved
            // values, so the form can tell "left on default" from "set to
            // the same number as the default".
            'settings' => [
                'name' => $shop->name,
                'timezone' => $shop->timezone,
                'stale_quote_days' => $shop->stale_quote_days,
                'win_rate_window_days' => $shop->win_rate_window_days,
            ],
            'defaults' => config('shop.defaults'),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(UpdateShopSettingsRequest $request): RedirectResponse
    {
        Auth::user()->currentShop->update($request->validated());

        return back()->with('status', 'Shop settings saved.');
    }
}
