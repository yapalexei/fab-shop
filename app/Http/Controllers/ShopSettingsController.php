<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Http\Requests\UpdateShopSettingsRequest;
use App\Models\DataExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
            // Owner-only: the shop's data controls (export / delete). Not
            // even sent to anyone else.
            'ownerControls' => Gate::allows('manage-shop') ? $this->ownerControls($shop) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerControls($shop): array
    {
        return [
            'exports' => DataExport::latest('id')->limit(5)->get()->map(fn (DataExport $export) => [
                'id' => $export->id,
                'status' => $export->status,
                'size_bytes' => $export->size_bytes,
                'created_at' => $export->created_at->toIso8601String(),
                'expires_at' => $export->expires_at?->toIso8601String(),
                'downloadable' => $export->isDownloadable(),
                'error' => $export->error,
            ]),
            'deletion' => [
                'scheduled_for' => $shop->deletion_scheduled_for?->toIso8601String(),
                'grace_days' => (int) config('shop.deletion_grace_days'),
                'shop_name' => $shop->name,
            ],
        ];
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
