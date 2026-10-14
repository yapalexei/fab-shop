<?php

namespace App\Support;

use App\Enums\Feature;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Support\Collection;

/**
 * Spots a shop paying for add-ons separately when a higher plan would cover
 * them for less money - or the same money with more included.
 *
 * Every figure is a monthly price in cents, read live from Plan and
 * FeatureSetting so the advice can't drift from what's actually charged.
 */
class PlanAdvisor
{
    /**
     * Advice for a shop about to add $feature as a separate add-on, or null
     * if subscribing separately is already the best deal.
     *
     * @return array<string, mixed>|null
     */
    public static function forNewAddon(Shop $shop, Feature $feature): ?array
    {
        $addons = self::activeAddons($shop)->push($feature)->unique();

        return self::best($shop, $addons, mustCover: collect([$feature]));
    }

    /**
     * Advice for a shop's add-ons as they stand today (the Add-ons page
     * banner), or null if there's nothing cheaper.
     *
     * @return array<string, mixed>|null
     */
    public static function forExistingAddons(Shop $shop): ?array
    {
        $addons = self::activeAddons($shop);

        return $addons->isEmpty() ? null : self::best($shop, $addons, mustCover: collect());
    }

    /**
     * @param  Collection<int, Feature>  $addons  every add-on the shop would be paying for separately
     * @param  Collection<int, Feature>  $mustCover  features the recommended plan must include
     * @return array<string, mixed>|null
     */
    private static function best(Shop $shop, Collection $addons, Collection $mustCover): ?array
    {
        FeatureSetting::ensureDefaults();
        $prices = FeatureSetting::priceMap();
        $addonCost = fn (Collection $features): int => $features->sum(fn (Feature $f) => $prices[$f->value]);

        $tiers = Plan::orderedByTier();
        // The plan the shop is actually on - maybe an older version of a tier
        // (or one since removed from the list), priced as it signed up.
        $currentPlan = $shop->plan;
        $currentRank = $currentPlan?->rank();
        $separateCents = ($currentPlan?->monthly_price_cents ?? 0) + $addonCost($addons);
        $memberCount = $shop->members()->count();

        $candidates = $tiers
            ->filter(fn (Plan $plan) => $currentRank === null || $plan->position > $currentRank)
            ->filter(fn (Plan $plan) => $shop->seat_limit_override !== null
                || $plan->user_limit === null
                || $plan->user_limit >= $memberCount)
            ->map(function (Plan $plan) use ($addons, $mustCover, $addonCost, $shop) {
                $bundled = $plan->features();
                $covered = $addons->filter(fn (Feature $f) => $bundled->contains($f));

                if ($covered->isEmpty() || $mustCover->contains(fn (Feature $f) => ! $bundled->contains($f))) {
                    return null;
                }

                $leftover = $addons->reject(fn (Feature $f) => $bundled->contains($f));
                // What the plan adds beyond what the shop would have had anyway.
                $currentFeatures = $shop->planFeatures()->merge($addons)->unique();
                $extras = $bundled
                    ->filter(fn (Feature $f) => $f->isShipped() && ! $currentFeatures->contains($f))
                    ->values();

                return [
                    'plan' => $plan,
                    'total' => $plan->monthly_price_cents + $addonCost($leftover),
                    'covered' => $covered->values(),
                    'leftover' => $leftover->values(),
                    'extras' => $extras,
                ];
            })
            ->filter()
            // Cheaper is a win; the same price only counts if it brings more.
            ->filter(fn (array $c) => $c['total'] < $separateCents
                || ($c['total'] === $separateCents && $c['extras']->isNotEmpty()))
            ->sortBy([['total', 'asc'], fn ($a, $b) => $b['extras']->count() <=> $a['extras']->count()]);

        $pick = $candidates->first();

        if ($pick === null) {
            return null;
        }

        $label = fn (Feature $f): string => $f->label();

        return [
            'plan_key' => $pick['plan']->key,
            'plan_name' => $pick['plan']->name,
            'plan_total_cents' => $pick['total'],
            'separate_total_cents' => $separateCents,
            'savings_cents' => $separateCents - $pick['total'],
            'covers' => $pick['covered']->map($label)->all(),
            'still_addons' => $pick['leftover']->map($label)->all(),
            'extras' => $pick['extras']->map($label)->all(),
        ];
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
            ->map(fn (string $feature) => Feature::from($feature))
            ->reject(fn (Feature $f) => $shop->hasPlanFeature($f))
            ->values();
    }

    /**
     * One-sentence version of advice, for the server-side refusal message.
     *
     * @param  array<string, mixed>  $advice
     */
    public static function sentence(array $advice): string
    {
        $fmt = fn (int $cents): string => '$'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
        $covers = implode(' + ', $advice['covers']);

        $money = $advice['savings_cents'] > 0
            ? 'saves '.$fmt($advice['savings_cents']).'/mo'
            : 'costs the same and includes more';

        return "The {$advice['plan_name']} plan includes {$covers} and {$money} compared with paying for add-ons separately.";
    }
}
