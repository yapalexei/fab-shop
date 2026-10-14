<?php

namespace App\Actions;

use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Support\Carbon;

/**
 * What happens to the shops already on an older version of a tier when a new
 * version of it is published (Plan::applyEdit()) - and, run again by the
 * hourly command, to any shop an earlier publish never reached:
 *
 * - Features: a shop keeps every feature it has and gains any the new version
 *   adds, immediately (and its owner is told exactly what is new,
 *   Shop::announcePlanChange()). A feature the new version drops stays.
 * - A LOWER price applies to every shop on the tier straight away. There is
 *   nothing for the shop to do and no plan to move to.
 * - A HIGHER price applies only if the admin said so when publishing
 *   (`apply_to_subscribers`), from `subscribers_effective_on`; no date means
 *   immediately. Owners are emailed when it is scheduled, ahead of the date,
 *   and the Terms promise `shop.price_increase_notice_days` of notice. The
 *   shop is moved by app:apply-scheduled-plan-changes when the date arrives.
 *   Without the flag, shops keep the price they signed up for.
 * - A shop whose seats would shrink (or whose extra seats would get dearer)
 *   is left where it is, and counted so the admin is told.
 * - The Free tier is where a shop lands after its trial: its price is not a
 *   subscription anyone agreed to, so a price change there never moves anyone.
 *
 * @phpstan-type Summary array{features: int, moved: int, scheduled: int, kept: int, grandfathered: int}
 */
class PropagateTierChange
{
    /**
     * @return Summary
     */
    public function __invoke(Plan $new): array
    {
        $summary = ['features' => 0, 'moved' => 0, 'scheduled' => 0, 'kept' => 0, 'grandfathered' => 0];
        $newFeatures = $new->features()->reject(fn ($feature) => $feature->isDiscontinued())->values();
        $olderVersions = Plan::query()->where('key', $new->key)->whereKeyNot($new->id)->pluck('id');

        // Shop is the tenant record itself, so there is no scope to bypass.
        Shop::query()->whereIn('plan_id', $olderVersions)->with(['plan', 'pendingPlan', 'owner'])->each(
            function (Shop $shop) use ($new, $newFeatures, &$summary): void {
                $have = $shop->planFeatures();
                $added = $newFeatures->reject(fn ($feature) => $have->contains($feature))->values();
                $changes = ['plan_name' => $new->name, 'added' => $added->map(fn ($feature) => $feature->value)->all()];

                if ($added->isNotEmpty()) {
                    $shop->forceFill([
                        'extra_features' => $have->merge($newFeatures)->unique()
                            ->reject(fn ($feature) => $shop->plan->hasFeature($feature))
                            ->map(fn ($feature) => $feature->value)->values()->all() ?: null,
                    ])->save();
                    $summary['features']++;
                }

                if ($new->key !== Plan::FALLBACK_KEY) {
                    $this->applyPrice($shop, $new, $changes, $summary);
                }

                // Only worth an email if something changes for the shop.
                if ($changes['added'] !== [] || isset($changes['price'])) {
                    $shop->announcePlanChange($changes);
                }
            },
        );

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  Summary  $summary
     */
    private function applyPrice(Shop $shop, Plan $new, array &$changes, array &$summary): void
    {
        $old = $shop->plan;

        if ($new->monthly_price_cents < $old->monthly_price_cents) {
            $this->moveNow($shop, $new, $changes, $summary);

            return;
        }

        if ($new->monthly_price_cents === $old->monthly_price_cents) {
            // The price is back where it was: an increase announced earlier is off.
            if ($shop->pending_plan_id !== null) {
                $shop->forceFill(['pending_plan_id' => null, 'pending_plan_effective_at' => null])->save();
            }

            return;
        }

        if (! $new->apply_to_subscribers) {
            $summary['grandfathered']++;

            return;
        }

        if ($shop->pending_plan_id === $new->id) {
            return;
        }

        $day = $new->subscribers_effective_on;

        if ($day === null || $day->lte(today())) {
            $this->moveNow($shop, $new, $changes, $summary);

            return;
        }

        if (! $this->keepsSeats($shop, $new)) {
            $summary['kept']++;

            return;
        }

        // Midnight at the start of that day in the shop's own timezone, so
        // the date it is shown with (and told about) is the date meant.
        $effective = Carbon::parse($day->toDateString(), $shop->localTimezone())->startOfDay()->utc();

        $shop->forceFill(['pending_plan_id' => $new->id, 'pending_plan_effective_at' => $effective])->save();
        $summary['scheduled']++;
        $this->describe($changes, $old, $new, $effective);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  Summary  $summary
     */
    private function moveNow(Shop $shop, Plan $new, array &$changes, array &$summary): void
    {
        if (! $this->keepsSeats($shop, $new)) {
            $summary['kept']++;

            return;
        }

        $old = $shop->plan;
        $shop->moveToPlan($new);
        $summary['moved']++;
        $this->describe($changes, $old, $new, now());
    }

    /**
     * What the owner is told about the price: from, to, and the moment it
     * takes (or took) effect - in the future for a scheduled increase.
     *
     * @param  array<string, mixed>  $changes
     */
    private function describe(array &$changes, Plan $old, Plan $new, Carbon $effective): void
    {
        $changes['price'] = [
            'from_cents' => $old->monthly_price_cents,
            'to_cents' => $new->monthly_price_cents,
            'effective_at' => $effective->toIso8601String(),
        ];

        if ($old->user_limit !== $new->user_limit) {
            $changes['seats'] = ['from' => $old->user_limit, 'to' => $new->user_limit];
        }
    }

    /** Whether moving to $new would take seats away from $shop (null seats = unlimited). */
    private function keepsSeats(Shop $shop, Plan $new): bool
    {
        $old = $shop->plan;

        $seatsHold = $new->user_limit === null
            || ($old->user_limit !== null && $new->user_limit >= $old->user_limit);

        $extraSeatsHold = $shop->extra_seats === 0
            || ($new->extra_seat_price_cents !== null && $new->extra_seat_price_cents <= ($old->extra_seat_price_cents ?? PHP_INT_MAX));

        return $seatsHold && $extraSeatsHold;
    }
}
