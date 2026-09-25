<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\ShopFeatureSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A shop's own add-ons page - subscribing to a Feature standalone, outside
 * whatever its plan already bundles. Owner-only (can:manage-shop route
 * middleware), same reasoning as inviting teammates: this changes what the
 * shop is committed to paying for, not just a personal setting.
 */
class ShopFeatureSubscriptionController extends Controller
{
    public function index(): Response
    {
        FeatureSetting::ensureDefaults();

        $shop = Auth::user()->currentShop;
        $settings = FeatureSetting::query()->get()->keyBy('feature');
        $bundled = $shop->onTrial() ? collect(Feature::cases()) : ($shop->plan?->features() ?? collect());
        $subscribed = $shop->featureSubscriptions()->whereNull('cancelled_at')->pluck('feature');

        return Inertia::render('Addons/Index', [
            'addons' => collect(Feature::cases())
                ->filter(fn (Feature $feature) => $feature->isShipped())
                ->map(fn (Feature $feature) => [
                    'feature' => $feature->value,
                    'label' => $feature->label(),
                    'tagline' => $feature->tagline(),
                    'priceCents' => $settings[$feature->value]->price_cents,
                    'bundledInPlan' => $bundled->contains($feature),
                    'subscribed' => $subscribed->contains($feature->value),
                ])
                ->values(),
        ]);
    }

    public function store(Feature $feature): RedirectResponse
    {
        abort_unless($feature->isShipped(), 404);

        $shop = Auth::user()->currentShop;

        if ($shop->plan?->hasFeature($feature)) {
            return back()->with('error', "{$feature->label()} is already included in your plan.");
        }

        ShopFeatureSubscription::query()->updateOrCreate(
            ['feature' => $feature->value],
            ['started_at' => now(), 'cancelled_at' => null],
        );

        return back()->with('status', "Subscribed to {$feature->label()}.");
    }

    public function destroy(Feature $feature): RedirectResponse
    {
        ShopFeatureSubscription::query()
            ->where('feature', $feature->value)
            ->update(['cancelled_at' => now()]);

        return back()->with('status', "Cancelled {$feature->label()}.");
    }
}
