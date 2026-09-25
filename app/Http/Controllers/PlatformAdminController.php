<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateShopPlanRequest;
use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Internal, cross-tenant tool for platform staff (App\Models\User::$is_platform_admin,
 * gated by the platform-admin Gate) - not a per-shop role, deliberately able
 * to see and act across every shop, which is why every query here reads
 * every shop rather than the current one.
 *
 * Revenue shown is potential/theoretical only (plan price x shop counts) -
 * not tied to Stripe, Square or any real collected-payment data, which is
 * explicitly deferred (see docs/product-desc.md's F10 section).
 */
class PlatformAdminController extends Controller
{
    public function index(): Response
    {
        $shops = Shop::with(['plan', 'owner'])->withCount('members')->orderByDesc('created_at')->get();

        return Inertia::render('PlatformAdmin/Index', [
            'shops' => $shops->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'owner_email' => $shop->owner->email,
                'plan_id' => $shop->plan_id,
                'plan_name' => $shop->plan?->name,
                'on_trial' => $shop->onTrial(),
                'trial_ends_at' => $shop->trial_ends_at,
                'is_onboarded' => $shop->isOnboarded(),
                'is_suspended' => $shop->isSuspended(),
                'member_count' => $shop->members_count,
                'seat_limit' => $shop->plan?->user_limit,
                'created_at' => $shop->created_at,
            ]),
            'plans' => Plan::orderedByTier()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
            ]),
            'revenue' => $this->revenueSummary($shops),
        ]);
    }

    public function suspend(Shop $shop): RedirectResponse
    {
        $shop->forceFill(['suspended_at' => now()])->save();

        return back()->with('status', "Suspended {$shop->name}.");
    }

    public function unsuspend(Shop $shop): RedirectResponse
    {
        $shop->forceFill(['suspended_at' => null])->save();

        return back()->with('status', "Unsuspended {$shop->name}.");
    }

    public function updatePlan(UpdateShopPlanRequest $request, Shop $shop): RedirectResponse
    {
        $shop->update(['plan_id' => $request->validated('plan_id')]);

        // A feature the shop was individually paying for as an add-on may
        // now be included in the new plan - cancel it so it's never billed
        // twice for the same thing.
        $cancelled = $shop->cancelSubscriptionsBundledInPlan();

        $status = "Updated {$shop->name}'s plan.";
        if ($cancelled > 0) {
            $status .= " Cancelled {$cancelled} add-on subscription(s) now included in the new plan.";
        }

        return back()->with('status', $status);
    }

    /**
     * Two numbers, both theoretical: what active (post-trial, unsuspended)
     * shops are nominally paying now, and what's sitting in trials if they
     * all convert. Neither is real collected revenue - see the class doc.
     *
     * @param  Collection<int, Shop>  $shops
     * @return array<string, int>
     */
    private function revenueSummary(Collection $shops): array
    {
        $billable = $shops->filter(fn (Shop $shop) => ! $shop->isSuspended());

        $activeMrrCents = $billable
            ->filter(fn (Shop $shop) => ! $shop->onTrial())
            ->sum(fn (Shop $shop) => $shop->plan?->monthly_price_cents ?? 0);

        $trialPotentialMrrCents = $billable
            ->filter(fn (Shop $shop) => $shop->onTrial())
            ->sum(fn (Shop $shop) => $shop->plan?->monthly_price_cents ?? 0);

        return [
            'active_mrr_cents' => (int) $activeMrrCents,
            'trial_potential_mrr_cents' => (int) $trialPotentialMrrCents,
        ];
    }
}
