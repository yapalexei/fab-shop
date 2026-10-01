<?php

namespace App\Http\Controllers;

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\ShopFeatureSubscription;
use App\Support\Billing;
use App\Support\PlanAdvisor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $subscriptions = $shop->featureSubscriptions()->active()->get()->keyBy('feature');

        return Inertia::render('Addons/Index', [
            'addons' => collect(Feature::cases())
                ->filter(fn (Feature $feature) => $feature->isShipped())
                ->map(fn (Feature $feature) => [
                    'feature' => $feature->value,
                    'label' => $feature->label(),
                    'tagline' => $feature->tagline(),
                    'priceCents' => $settings[$feature->value]->price_cents,
                    'bundledInPlan' => $bundled->contains($feature),
                    'subscribed' => $subscriptions->has($feature->value) && ! $subscriptions->get($feature->value)->isEnding(),
                    // Cancelled but still paid through - access ends on this date.
                    'endsAt' => $subscriptions->get($feature->value)?->isEnding() ? $subscriptions->get($feature->value)->ends_at->toIso8601String() : null,
                    // What subscribing costs today, per billing interval (they only
                    // differ when it starts a new period).
                    'quotes' => [
                        'monthly' => Billing::addOnQuote($shop, (int) $settings[$feature->value]->price_cents, BillingInterval::Monthly),
                        'annual' => Billing::addOnQuote($shop, (int) $settings[$feature->value]->price_cents, BillingInterval::Annual),
                    ],
                    // A plan that beats paying for this add-on separately (or
                    // null) - the page confirms with the owner before subscribing.
                    'advice' => $bundled->contains($feature) || $subscriptions->has($feature->value)
                        ? null
                        : PlanAdvisor::forNewAddon($shop, $feature),
                ])
                ->values(),
            // Existing add-ons that a plan would now cover for less.
            'bannerAdvice' => PlanAdvisor::forExistingAddons($shop),
            // Whether a billing period is already running (add-ons then share
            // its renewal date) or the next purchase starts one.
            'hasBillingPeriod' => Billing::periodEnd($shop) !== null,
            'billingInterval' => $shop->billingInterval()->value,
            'annualMonthsCharged' => Billing::annualMonthsCharged(),
        ]);
    }

    public function store(Request $request, Feature $feature): RedirectResponse
    {
        abort_unless($feature->isShipped(), 404);

        $shop = Auth::user()->currentShop;

        if ($shop->plan?->hasFeature($feature)) {
            return back()->with('error', "{$feature->label()} is already included in your plan.");
        }

        // Cancelled but still paid through: taking it back costs nothing.
        $existing = ShopFeatureSubscription::query()->where('feature', $feature->value)->first();
        if ($existing?->isEnding()) {
            $existing->update(['cancelled_at' => null, 'ends_at' => null]);

            return back()->with('status', "{$feature->label()} will keep renewing.");
        }

        // Server-side half of the "a plan is cheaper" warning: the page asks
        // the owner first and re-posts with confirm_separate, but a request
        // that skips the page gets the same stop.
        $advice = PlanAdvisor::forNewAddon($shop, $feature);
        if ($advice !== null && ! $request->boolean('confirm_separate')) {
            return back()->with('error', PlanAdvisor::sentence($advice));
        }

        $interval = BillingInterval::tryFrom((string) $request->input('interval')) ?? $shop->billingInterval();
        $quote = Billing::addOnQuote($shop, FeatureSetting::priceFor($feature), $interval);

        ShopFeatureSubscription::query()->updateOrCreate(
            ['feature' => $feature->value],
            ['started_at' => now(), 'cancelled_at' => null, 'ends_at' => null],
        );

        // The first purchase starts the shop's billing period; later ones
        // join the one already running.
        if ($quote['starts_new_period']) {
            $shop->update([
                'billing_interval' => $quote['interval'],
                'subscription_renews_at' => Carbon::parse($quote['renews_at']),
            ]);
        }

        return back()->with('status', "Subscribed to {$feature->label()}.");
    }

    public function destroy(Feature $feature): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        // Paid through the end of the current period; no refund for it.
        $endsAt = Billing::periodEnd($shop) ?? now();

        $cancelled = ShopFeatureSubscription::query()
            ->where('feature', $feature->value)
            ->active()
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => now(), 'ends_at' => $endsAt]);

        if ($cancelled === 0) {
            return back();
        }

        return back()->with('status', $endsAt->isFuture()
            ? "{$feature->label()} cancelled - you keep it until {$endsAt->toFormattedDateString()}."
            : "Cancelled {$feature->label()}.");
    }
}
