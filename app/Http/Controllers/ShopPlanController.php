<?php

namespace App\Http\Controllers;

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;
use App\Support\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
        $currentIndex = $this->currentIndex($shop, $tiers);
        $current = $currentIndex === false ? null : $tiers[$currentIndex];
        $interval = $shop->billingInterval();

        return Inertia::render('ShopPlan/Index', [
            'currentPlanKey' => $current?->key,
            'billingInterval' => $interval->value,
            'renewsAt' => Billing::periodEnd($shop)?->toIso8601String(),
            'annualMonthsCharged' => Billing::annualMonthsCharged(),
            'plans' => $tiers->map(fn (Plan $plan, int $index) => [
                'key' => $plan->key,
                'name' => $plan->name,
                'user_limit' => $plan->user_limit,
                'monthly_price_cents' => $plan->monthly_price_cents,
                'annual_price_cents' => Billing::periodPriceCents($plan->monthly_price_cents, BillingInterval::Annual),
                'features' => $plan->features()
                    ->filter(fn (Feature $feature) => $feature->isShipped())
                    ->map(fn (Feature $feature) => $feature->label())
                    ->values(),
                'upgradeable' => $currentIndex === false || $index > $currentIndex,
                // What switching to this plan costs today, per interval.
                'quotes' => ($currentIndex === false || $index > $currentIndex) ? [
                    'monthly' => Billing::planChangeQuote($shop, $plan, BillingInterval::Monthly),
                    'annual' => Billing::planChangeQuote($shop, $plan, BillingInterval::Annual),
                ] : null,
            ])->values(),
            'seats' => $this->seats($shop),
            // Same plan, billed yearly instead (only offered from monthly).
            'annualSwitch' => $current !== null && $current->monthly_price_cents > 0 && $interval === BillingInterval::Monthly
                ? Billing::planChangeQuote($shop, $current, BillingInterval::Annual)
                : null,
        ]);
    }

    /**
     * The seats card: what's included, what's bought, and what adding 1..N
     * more would cost today (proration is linear in N, so the page can pick
     * the right quote without a round trip).
     *
     * @return array<string, mixed>
     */
    private function seats(Shop $shop): array
    {
        $plan = $shop->plan;
        $price = $plan?->extra_seat_price_cents;
        $purchasable = $plan !== null && $price !== null && $plan->user_limit !== null;
        $room = max(0, config('shop.max_extra_seats') - $shop->extra_seats);
        $canBuy = $purchasable && Billing::periodEnd($shop) !== null;

        return [
            'used' => $shop->seatsUsed(),
            'included' => $plan?->user_limit,
            'extra' => $shop->extra_seats,
            'limit' => $shop->seatLimit(),
            'purchasable' => $purchasable,
            // Seats are billed with the plan's period, so they need one.
            'can_buy' => $canBuy,
            'price_cents' => $price,
            'min_extra' => $purchasable ? max(0, $shop->seatsUsed() - $plan->user_limit) : 0,
            'quotes' => $canBuy
                ? collect(range(1, min(10, $room)))->mapWithKeys(fn (int $n) => [$n => Billing::seatQuote($shop, $shop->extra_seats + $n)])->all()
                : [],
        ];
    }

    public function update(Request $request): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        $tiers = Plan::orderedByTier();
        $currentIndex = $this->currentIndex($shop, $tiers);

        $validated = $request->validate([
            'plan' => ['required', 'in:'.$tiers->pluck('key')->implode(',')],
            'interval' => ['nullable', 'in:monthly,annual'],
        ]);

        $plan = $tiers->firstWhere('key', $validated['plan']);
        $planIndex = $tiers->search(fn (Plan $p) => $p->id === $plan->id);
        $interval = BillingInterval::tryFrom($validated['interval'] ?? '') ?? $shop->billingInterval();

        // A higher tier, or the same plan moving from monthly to annual.
        $isUpgrade = $currentIndex === false || $planIndex > $currentIndex;
        $isAnnualSwitch = $planIndex === $currentIndex
            && $shop->billingInterval() === BillingInterval::Monthly
            && $interval === BillingInterval::Annual;

        if (! $isUpgrade && ! $isAnnualSwitch) {
            throw ValidationException::withMessages(['plan' => 'You can only move to a higher plan, or switch your current plan to annual billing.']);
        }

        $quote = Billing::planChangeQuote($shop, $plan, $interval);

        $shop->update(['plan_id' => $plan->id, 'billing_interval' => $interval]);

        if ($quote['starts_new_period']) {
            $shop->update(['subscription_renews_at' => Carbon::parse($quote['renews_at'])]);

            // Add-ons already cancelled and riding out the old period end with it.
            ShopFeatureSubscription::query()
                ->whereNotNull('cancelled_at')
                ->where('ends_at', '>', now())
                ->update(['ends_at' => now()]);
        }

        // An add-on the new plan already bundles must not keep billing.
        $shop->cancelSubscriptionsBundledInPlan();

        return redirect()->route('dashboard')->with('status', $isAnnualSwitch && ! $isUpgrade
            ? "Your {$plan->name} plan now bills annually."
            : "Your shop is now on the {$plan->name} plan.");
    }

    /**
     * @param  Collection<int, Plan>  $tiers
     */
    private function currentIndex(Shop $shop, Collection $tiers): int|false
    {
        return $tiers->search(fn (Plan $plan) => $plan->id === $shop->plan_id);
    }
}
