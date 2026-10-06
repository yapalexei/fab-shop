<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\PlatformSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public marketing page at "/" - what the product does, and what each
 * subscription tier includes. Tier contents are read live from the same
 * Plan/Feature data Shop::hasFeature() enforces (seeded by PlanSeeder), so
 * this page can never claim a tier includes something the app doesn't
 * actually grant it.
 */
class MarketingController extends Controller
{
    public function __invoke(): Response
    {
        $prices = FeatureSetting::priceMap();
        $features = $this->shippedFeatures();

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'features' => $features->map(fn (Feature $feature) => [
                'key' => $feature->value,
                'label' => $feature->label(),
                'tagline' => $feature->tagline(),
                'shipped' => $feature->isShipped(),
            ]),
            'trialDays' => PlatformSetting::defaultTrialDays(),
            'tiers' => Plan::orderedByTier()->map(fn (Plan $plan) => $this->tier($plan, $prices, $features)),
        ]);
    }

    /**
     * Unshipped features (currently just Mobile/API access) are left off
     * the marketing page entirely rather than teased as "coming soon" - a
     * deliberate choice, not an oversight; isShipped()'s "coming soon"
     * rendering in Welcome.tsx stays in place for whenever that changes.
     *
     * @return Collection<int, Feature>
     */
    private function shippedFeatures(): Collection
    {
        return collect(Feature::cases())->filter(fn (Feature $feature) => $feature->isShipped())->values();
    }

    /**
     * @param  Collection<string, int>  $prices
     * @param  Collection<int, Feature>  $features
     * @return array<string, mixed>
     */
    private function tier(Plan $plan, Collection $prices, Collection $features): array
    {
        // The free plan has no permanent features of its own - a new shop's
        // access during its 14-day trial comes from Shop::onTrial(), not
        // from being on this plan (see PlanSeeder's own note). Marketed as
        // "everything, for 14 days" rather than a checklist of zero.
        $isTrialOnly = $plan->key === 'free';
        $granted = $isTrialOnly ? collect() : $plan->features();

        // What the bundled features would cost bought individually as
        // add-ons, vs. what the plan actually charges - the "save $X/mo
        // bundled" figure. Never negative: a plan priced above the sum of
        // its own features' add-on prices (shouldn't happen, but pricing is
        // still placeholder/admin-edited) just shows no savings rather than
        // a confusing negative number.
        $standaloneValueCents = $granted->sum(fn (Feature $feature) => $prices[$feature->value]);
        $savingsCents = max(0, $standaloneValueCents - $plan->monthly_price_cents);

        return [
            'key' => $plan->key,
            'name' => $plan->name,
            'userLimit' => $plan->user_limit,
            'isTrialOnly' => $isTrialOnly,
            'monthlyPriceCents' => $plan->monthly_price_cents,
            'extraSeatPriceCents' => $plan->extra_seat_price_cents,
            'savingsCents' => $savingsCents,
            'features' => $features->map(fn (Feature $feature) => [
                'key' => $feature->value,
                'included' => $granted->contains($feature),
            ]),
        ];
    }
}
