<?php

namespace App\Support;

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a subscription change costs *today*, and when the shop renews next -
 * the numbers the confirmation dialogs show before anyone commits. Nothing
 * here charges a card (no payment processor is connected yet); once one is,
 * its invoices should agree with these figures.
 *
 * The model: a shop has ONE billing period (shops.subscription_renews_at is
 * its end; the interval is monthly or annual) shared by its plan and every
 * add-on.
 * - Adding something mid-period charges the remaining fraction of the period
 *   immediately, then the full price at each renewal.
 * - With no live period (first purchase, or it lapsed) a new period starts
 *   today and the first charge is the full period price.
 * - Cancelling an add-on keeps it until the period ends; no refund.
 * All amounts are cents.
 */
final class Billing
{
    public static function annualMonthsCharged(): int
    {
        return config('shop.annual_months_charged');
    }

    /** What one billing period of something costing $monthlyCents/month comes to. */
    public static function periodPriceCents(int $monthlyCents, BillingInterval $interval): int
    {
        return $interval === BillingInterval::Annual
            ? $monthlyCents * self::annualMonthsCharged()
            : $monthlyCents;
    }

    /** End of the shop's current paid period, or null when there isn't a live one. */
    public static function periodEnd(Shop $shop): ?Carbon
    {
        return $shop->subscription_renews_at?->isFuture() ? $shop->subscription_renews_at : null;
    }

    /**
     * Share of the current period still ahead (0-1), and whole days left.
     *
     * @return array{fraction: float, daysLeft: int}|null null with no live period
     */
    public static function remaining(Shop $shop): ?array
    {
        $end = self::periodEnd($shop);

        if ($end === null) {
            return null;
        }

        $start = $end->copy()->subMonthsNoOverflow($shop->billingInterval()->months());
        $total = max(1, $start->diffInSeconds($end));
        $left = max(0, now()->diffInSeconds($end));

        return [
            'fraction' => min(1.0, $left / $total),
            'daysLeft' => (int) ceil($left / 86400),
        ];
    }

    /**
     * Cost of adding an add-on priced $monthlyCents/month right now. $interval
     * only matters when this starts a new period (otherwise the shop's own
     * interval stands).
     *
     * @return array<string, mixed>
     */
    public static function addOnQuote(Shop $shop, int $monthlyCents, ?BillingInterval $interval = null): array
    {
        $remaining = self::remaining($shop);
        $interval = $remaining === null ? ($interval ?? $shop->billingInterval()) : $shop->billingInterval();
        $full = self::periodPriceCents($monthlyCents, $interval);

        return self::result(
            charge: $remaining === null ? $full : (int) round($full * $remaining['fraction']),
            periodPrice: $full,
            interval: $interval,
            renewsAt: $remaining === null ? now()->addMonthsNoOverflow($interval->months()) : self::periodEnd($shop),
            remaining: $remaining,
        );
    }

    /**
     * Cost of moving the shop to $to on $interval right now (an upgrade, or
     * monthly -> annual on the same plan). Unused time on what's being
     * replaced is credited; add-ons the new plan bundles are dropped (their
     * unused time credited too) and the rest carry over.
     *
     * @return array<string, mixed>
     */
    public static function planChangeQuote(Shop $shop, Plan $to, BillingInterval $interval): array
    {
        $prices = FeatureSetting::priceMap();
        $current = Plan::query()->find($shop->plan_id);
        $bundled = $to->features();
        $addons = self::activeAddons($shop);
        $currentInterval = $shop->billingInterval();
        $remaining = self::remaining($shop);
        $sameInterval = $remaining !== null && $currentInterval === $interval;

        $addonCost = fn (Collection $features, BillingInterval $i): int => (int) $features
            ->sum(fn (Feature $f) => self::periodPriceCents($prices[$f->value], $i));

        $keptAddons = $addons->reject(fn (Feature $f) => $bundled->contains($f));
        $droppedAddons = $addons->filter(fn (Feature $f) => $bundled->contains($f));
        $newRecurring = self::periodPriceCents($to->monthly_price_cents + self::seatsMonthlyCents($shop, $to), $interval) + $addonCost($keptAddons, $interval);

        if ($sameInterval) {
            // Same period: pay the price difference for the time left.
            $delta = self::periodPriceCents(
                ($to->monthly_price_cents + self::seatsMonthlyCents($shop, $to)) - (($current?->monthly_price_cents ?? 0) + ($current ? self::seatsMonthlyCents($shop, $current) : 0)),
                $interval,
            );
            $net = (int) round($delta * $remaining['fraction'])
                - (int) round($addonCost($droppedAddons, $interval) * $remaining['fraction']);
            $renewsAt = self::periodEnd($shop);
        } else {
            // A fresh period starts today; whatever was left of the old one is credited.
            $unused = 0;
            if ($remaining !== null) {
                $paid = self::periodPriceCents(($current?->monthly_price_cents ?? 0) + ($current ? self::seatsMonthlyCents($shop, $current) : 0), $currentInterval) + $addonCost($addons, $currentInterval);
                $unused = (int) round($paid * $remaining['fraction']);
            }
            $net = $newRecurring - $unused;
            $renewsAt = now()->addMonthsNoOverflow($interval->months());
        }

        return self::result(
            charge: max(0, $net),
            periodPrice: $newRecurring,
            interval: $interval,
            renewsAt: $renewsAt,
            remaining: $sameInterval ? $remaining : null,
            credit: max(0, -$net),
            startsNewPeriod: ! $sameInterval,
        );
    }

    /**
     * Cost of changing the shop's extra seats to $newExtra right now. Only an
     * increase charges (prorated for the time left, like an add-on); a
     * decrease is free and takes effect at once, with no refund - the same
     * "no refund" rule as cancelling an add-on. Needs a live billing period:
     * seats ride on the plan's period, they don't start one.
     *
     * @return array<string, mixed>
     */
    public static function seatQuote(Shop $shop, int $newExtra): array
    {
        $price = $shop->plan?->extra_seat_price_cents ?? 0;
        $added = max(0, $newExtra - $shop->extra_seats);

        return self::addOnQuote($shop, $added * $price);
    }

    /** What the shop's extra seats add to its monthly bill under $plan's seat price. */
    private static function seatsMonthlyCents(Shop $shop, Plan $plan): int
    {
        return $shop->extra_seats * ($plan->extra_seat_price_cents ?? 0);
    }

    /**
     * @return Collection<int, Feature>
     */
    private static function activeAddons(Shop $shop): Collection
    {
        return $shop->featureSubscriptions()
            ->withoutGlobalScopes()
            ->active()
            ->pluck('feature')
            ->map(fn (string $feature) => Feature::from($feature));
    }

    /**
     * @param  array{fraction: float, daysLeft: int}|null  $remaining
     * @return array<string, mixed>
     */
    private static function result(int $charge, int $periodPrice, BillingInterval $interval, CarbonInterface $renewsAt, ?array $remaining, int $credit = 0, ?bool $startsNewPeriod = null): array
    {
        return [
            // Due now.
            'charge_cents' => $charge,
            // Left over after covering the change - applied to the next invoice.
            'credit_cents' => $credit,
            // What the item(s) cost every period from the renewal on.
            'period_price_cents' => $periodPrice,
            'interval' => $interval->value,
            'renews_at' => $renewsAt->toIso8601String(),
            'prorated' => $remaining !== null,
            'days_left' => $remaining['daysLeft'] ?? null,
            'starts_new_period' => $startsNewPeriod ?? $remaining === null,
        ];
    }
}
