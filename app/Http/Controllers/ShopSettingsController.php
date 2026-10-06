<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
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
                // A plain on/off switch, so the resolved value (not null).
                'job_board_drag_enabled' => $shop->jobBoardDragEnabled(),
                'quote_emailing_enabled' => (bool) $shop->quote_emailing_enabled,
            ],
            // Whether the plan includes it - when not, the switch is shown
            // disabled with a note about which plan has it.
            'quoteEmailingAvailable' => $shop->hasFeature(Feature::QuoteEmailing),
            'defaults' => config('shop.defaults'),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(UpdateShopSettingsRequest $request): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        $settings = $request->validated();

        // Not in the plan: the setting can't be changed (not even by a
        // hand-built request), whatever the UI shows.
        if (! $shop->hasFeature(Feature::QuoteEmailing)) {
            unset($settings['quote_emailing_enabled']);
        }

        $shop->update($settings);

        return back()->with('status', 'Shop settings saved.');
    }
}
