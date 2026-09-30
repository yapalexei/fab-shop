<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A shop's own plan page - where the header's Upgrade button lands.
 * Owner-only (can:manage-shop route middleware), same as add-ons: it
 * changes what the shop is committed to paying for.
 *
 * Upgrade-only on purpose: moving to a *higher* tier is the one change with
 * no data-loss questions. Like add-ons, nothing is charged yet - no payment
 * processor is connected - but the plan controls access exactly as a paid
 * one would.
 */
class ShopPlanController extends Controller
{
    public function index(): Response
    {
        $shop = Auth::user()->currentShop;
        $tiers = Plan::orderedByTier();
        $currentIndex = $tiers->search(fn (Plan $plan) => $plan->id === $shop->plan_id);

        return Inertia::render('ShopPlan/Index', [
            'currentPlanKey' => $currentIndex === false ? null : $tiers[$currentIndex]->key,
            'plans' => $tiers->map(fn (Plan $plan, int $index) => [
                'key' => $plan->key,
                'name' => $plan->name,
                'user_limit' => $plan->user_limit,
                'monthly_price_cents' => $plan->monthly_price_cents,
                'features' => $plan->features()
                    ->filter(fn (Feature $feature) => $feature->isShipped())
                    ->map(fn (Feature $feature) => $feature->label())
                    ->values(),
                'upgradeable' => $currentIndex === false || $index > $currentIndex,
            ])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        $tiers = Plan::orderedByTier();
        $currentIndex = $tiers->search(fn (Plan $plan) => $plan->id === $shop->plan_id);
        $allowed = $tiers->filter(fn (Plan $plan, int $index) => $currentIndex === false || $index > $currentIndex);

        $validated = $request->validate([
            'plan' => ['required', 'in:'.$allowed->pluck('key')->implode(',')],
        ]);

        $plan = $allowed->firstWhere('key', $validated['plan']);
        $shop->update(['plan_id' => $plan->id]);

        // An add-on the new plan already bundles must not keep billing.
        $shop->cancelSubscriptionsBundledInPlan();

        return redirect()->route('dashboard')->with('status', "Your shop is now on the {$plan->name} plan.");
    }
}
