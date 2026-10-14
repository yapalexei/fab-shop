<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Models\Shop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:announce-discontinued-features')]
#[Description('Stop billing for features we no longer support and tell each affected owner, once')]
class AnnounceDiscontinuedFeatures extends Command
{
    /**
     * Access already stops the moment Feature::isDiscontinued() is true
     * (Shop::hasFeature()); this is the human side. For every shop that had a
     * discontinued feature - in its plan, carried over from an earlier plan
     * version, or as a paid add-on - it cancels the add-on so nothing keeps
     * billing, drops the feature from the shop's carried-over list, and emails
     * the owner. `notified_discontinued_features` makes it safe to run again.
     */
    public function handle(): void
    {
        $discontinued = collect(Feature::cases())->filter(fn (Feature $feature) => $feature->isDiscontinued());

        if ($discontinued->isEmpty()) {
            return;
        }

        Shop::query()->each(function (Shop $shop) use ($discontinued): void {
            $told = collect($shop->notified_discontinued_features ?? []);

            $lost = $discontinued
                ->reject(fn (Feature $feature) => $told->contains($feature->value))
                ->filter(fn (Feature $feature) => ($shop->plan?->hasFeature($feature) ?? false)
                    || $shop->extraFeatures()->contains($feature)
                    || $shop->hasActiveFeatureSubscription($feature))
                ->values();

            if ($lost->isEmpty()) {
                return;
            }

            $values = $lost->map(fn (Feature $feature) => $feature->value);

            // Never bill for something that no longer exists.
            $shop->featureSubscriptions()->withoutGlobalScopes()->whereIn('feature', $values)->active()
                ->update(['cancelled_at' => now(), 'ends_at' => now()]);

            $shop->forceFill([
                'extra_features' => $shop->extraFeatures()->reject(fn (Feature $f) => $values->contains($f->value))
                    ->map(fn (Feature $f) => $f->value)->values()->all() ?: null,
                'notified_discontinued_features' => $told->merge($values)->unique()->values()->all(),
            ])->save();

            $shop->announcePlanChange(['plan_name' => $shop->plan?->name ?? $shop->name, 'retired' => $values->all()]);
        });
    }
}
