<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\Plan;
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
    /**
     * Tiers are shown in this fixed order regardless of table order. A
     * missing plan (e.g. a fresh install that hasn't run PlanSeeder yet)
     * is simply left off rather than erroring the page.
     */
    private const TIER_ORDER = ['free', 'start-up', 'pro'];

    public function __invoke(): Response
    {
        $plans = Plan::query()->get()->keyBy('key');

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'features' => collect(Feature::cases())->map(fn (Feature $feature) => [
                'key' => $feature->value,
                'label' => $feature->label(),
                'tagline' => $feature->tagline(),
                'shipped' => $feature->isShipped(),
            ]),
            'tiers' => collect(self::TIER_ORDER)
                ->filter(fn (string $key) => $plans->has($key))
                ->map(fn (string $key) => $this->tier($plans[$key]))
                ->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tier(Plan $plan): array
    {
        // The free plan has no permanent features of its own - a new shop's
        // access during its 14-day trial comes from Shop::onTrial(), not
        // from being on this plan (see PlanSeeder's own note). Marketed as
        // "everything, for 14 days" rather than a checklist of zero.
        $isTrialOnly = $plan->key === 'free';
        $granted = $isTrialOnly ? collect() : $plan->features();

        return [
            'key' => $plan->key,
            'name' => $plan->name,
            'userLimit' => $plan->user_limit,
            'isTrialOnly' => $isTrialOnly,
            'features' => collect(Feature::cases())->map(fn (Feature $feature) => [
                'key' => $feature->value,
                'included' => $granted->contains($feature),
            ]),
        ];
    }
}
