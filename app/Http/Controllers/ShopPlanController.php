<?php

namespace App\Http\Controllers;

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;
use App\Support\Billing;
use App\Support\Consent;
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
        // What the shop is actually on - which may be an older version of a
        // tier (or one since removed from the list), with its own price.
        $current = $shop->plan;
        $currentRank = $current?->rank();
        $interval = $shop->billingInterval();

        return Inertia::render('ShopPlan/Index', [
            'currentPlanId' => $current?->id,
            'currentPlan' => $current ? $this->planRow($current, false, null, $shop->planFeatures()) : null,
            'planNotice' => $this->planNotice($shop),
            'terms' => Consent::subscriptionTerms(Auth::user()),
            // A price increase announced for a date ahead (lower prices apply at once).
            'pendingChange' => $shop->pending_plan_id ? [
                'plan_id' => $shop->pending_plan_id,
                'from_monthly_cents' => $current->monthly_price_cents,
                'from_annual_cents' => Billing::periodPriceCents($current->monthly_price_cents, BillingInterval::Annual),
                'name' => $shop->pendingPlan->name,
                'monthly_price_cents' => $shop->pendingPlan->monthly_price_cents,
                'annual_price_cents' => Billing::periodPriceCents($shop->pendingPlan->monthly_price_cents, BillingInterval::Annual),
                'effective_at' => $shop->pending_plan_effective_at->toIso8601String(),
            ] : null,
            'billingInterval' => $interval->value,
            'renewsAt' => Billing::periodEnd($shop)?->toIso8601String(),
            'annualMonthsCharged' => Billing::annualMonthsCharged(),
            'plans' => $tiers->map(function (Plan $plan) use ($shop, $currentRank) {
                $upgradeable = $currentRank === null || $plan->position > $currentRank;

                return $this->planRow($plan, $upgradeable, $upgradeable ? [
                    // What switching to this plan costs today, per interval.
                    'monthly' => Billing::planChangeQuote($shop, $plan, BillingInterval::Monthly),
                    'annual' => Billing::planChangeQuote($shop, $plan, BillingInterval::Annual),
                ] : null);
            })->values(),
            'calculator' => $this->calculator($shop),
            'seats' => $this->seats($shop),
            // Same plan, billed yearly instead (only offered from monthly).
            'annualSwitch' => $current !== null && $current->monthly_price_cents > 0 && $interval === BillingInterval::Monthly
                ? Billing::planChangeQuote($shop, $current, BillingInterval::Annual)
                : null,
        ]);
    }

    /**
     * What the cost calculator needs besides the plans: every feature that
     * can be bought on its own with its add-on price, the features this shop
     * uses today (the calculator's starting point - its plan plus add-ons, not
     * whatever a trial happens to unlock), and how many people it has.
     *
     * @return array<string, mixed>
     */
    private function calculator(Shop $shop): array
    {
        $prices = FeatureSetting::priceMap();
        $shipped = collect(Feature::cases())->filter(fn (Feature $feature) => $feature->isShipped());

        $addons = $shop->featureSubscriptions()->active()->pluck('feature')
            ->map(fn (string $value) => Feature::tryFrom($value))->filter();

        return [
            'features' => $shipped->map(fn (Feature $feature) => [
                'key' => $feature->value,
                'label' => $feature->label(),
                'addonCents' => (int) $prices[$feature->value],
            ])->values(),
            'needed' => $shop->planFeatures()->merge($addons)->unique()
                ->filter(fn (Feature $feature) => $feature->isShipped())
                ->map(fn (Feature $feature) => $feature->value)->values(),
            'people' => max(1, $shop->seatsUsed()),
        ];
    }

    /**
     * The latest "what changed on your plan" summary, with feature names
     * ready to show. Features that no longer exist are dropped quietly.
     *
     * @return array<string, mixed>|null
     */
    private function planNotice(Shop $shop): ?array
    {
        $notice = $shop->plan_notice;

        if (! $notice) {
            return null;
        }

        $features = fn (array $values, bool $tagline) => collect($values)
            ->map(fn (string $value) => Feature::tryFrom($value))
            ->filter()
            ->map(fn (Feature $feature) => $tagline
                ? ['label' => $feature->label(), 'tagline' => $feature->tagline()]
                : ['label' => $feature->label()])
            ->values();

        return [
            'plan_name' => $notice['plan_name'],
            'added' => $features($notice['added'] ?? [], true),
            'retired' => $features($notice['retired'] ?? [], false),
            'price' => $notice['price'] ?? null,
            'seats' => $notice['seats'] ?? null,
        ];
    }

    /** The owner has read it. */
    public function dismissNotice(): RedirectResponse
    {
        Auth::user()->currentShop->forceFill(['plan_notice' => null])->save();

        return back();
    }

    /**
     * @param  array<string, mixed>|null  $quotes
     * @param  Collection<int, Feature>|null  $features  what the plan gives this shop, when that isn't just the row's own
     * @return array<string, mixed>
     */
    private function planRow(Plan $plan, bool $upgradeable, ?array $quotes, ?Collection $features = null): array
    {
        return [
            'id' => $plan->id,
            'key' => $plan->key,
            'name' => $plan->name,
            'user_limit' => $plan->user_limit,
            'monthly_price_cents' => $plan->monthly_price_cents,
            'annual_price_cents' => Billing::periodPriceCents($plan->monthly_price_cents, BillingInterval::Annual),
            'features' => ($features ?? $plan->features())
                ->filter(fn (Feature $feature) => $feature->isShipped())
                ->map(fn (Feature $feature) => $feature->label())
                ->values(),
            'extra_seat_price_cents' => $plan->extra_seat_price_cents,
            // Machine-readable (the labels above are for the cards) so the
            // comparison table can tick features per plan.
            'feature_keys' => ($features ?? $plan->features())
                ->filter(fn (Feature $feature) => $feature->isShipped())
                ->map(fn (Feature $feature) => $feature->value)
                ->values(),
            'available_until' => $plan->show_expiry ? $plan->expires_on?->toDateString() : null,
            'upgradeable' => $upgradeable,
            'quotes' => $quotes,
        ];
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
        $currentRank = $shop->plan?->rank();

        // A tier the shop is already on is a valid answer even if it has
        // since been removed from the list (that's how it switches to annual).
        $ownKey = $shop->plan?->key;
        $validated = $request->validate([
            'plan' => ['required', 'in:'.$tiers->pluck('key')->push($ownKey)->filter()->unique()->implode(',')],
            'interval' => ['nullable', 'in:monthly,annual'],
        ]);

        $interval = BillingInterval::tryFrom($validated['interval'] ?? '') ?? $shop->billingInterval();

        // The shop's own plan moving from monthly to annual - billed at the
        // price it signed up for, not the tier's current price.
        $isAnnualSwitch = $validated['plan'] === $ownKey
            && $shop->billingInterval() === BillingInterval::Monthly
            && $interval === BillingInterval::Annual;

        $plan = $isAnnualSwitch ? $shop->plan : $tiers->firstWhere('key', $validated['plan']);

        // Otherwise only a higher tier. Picking the tier it's already on is
        // not a move: its price is what the shop signed up for until it leaves.
        $isUpgrade = $plan !== null && ($currentRank === null || $plan->position > $currentRank);

        if (! $isUpgrade && ! $isAnnualSwitch) {
            throw ValidationException::withMessages(['plan' => 'You can only move to a higher plan, or switch your current plan to annual billing.']);
        }

        Consent::requireAgreementToSubscribe($request, Auth::user(), $shop);

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
}
