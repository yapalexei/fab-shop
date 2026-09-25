<?php

namespace App\Models;

use App\Enums\Feature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Admin-editable price and trial length for a Feature (App\Enums\Feature).
 * One row per Feature case. Feature itself stays a plain PHP enum - the
 * fixed registry of what features exist, referenced by gates/middleware in
 * code - this table only holds the parts a platform admin can change at
 * runtime without a deploy, same division of responsibility Plan has
 * between the enum and the plan_feature pivot.
 */
class FeatureSetting extends Model
{
    protected $fillable = ['feature', 'price_cents', 'trial_days'];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'trial_days' => 'integer',
        ];
    }

    /**
     * How many days $feature stays free during a shop's trial. Falls back
     * to Shop::TRIAL_DAYS when the feature has no row yet (e.g. a fresh
     * install that hasn't run ensureDefaults()), so gating never breaks
     * just because the admin-editable row is missing.
     */
    public static function trialDaysFor(Feature $feature): int
    {
        return static::query()->where('feature', $feature->value)->value('trial_days') ?? Shop::TRIAL_DAYS;
    }

    /**
     * Every feature's trial length in one query, keyed by Feature::value -
     * for Shop::enabledFeatures(), which otherwise checks every feature and
     * would run trialDaysFor()'s query once per feature per page load.
     *
     * @return Collection<string, int>
     */
    public static function trialDaysMap(): Collection
    {
        $rows = static::query()->pluck('trial_days', 'feature');

        return collect(Feature::cases())->mapWithKeys(
            fn (Feature $feature) => [$feature->value => $rows[$feature->value] ?? Shop::TRIAL_DAYS],
        );
    }

    /**
     * Give every Feature case a settings row if it doesn't have one yet -
     * idempotent, same pattern as MaterialTypes::seedDefaults(), so adding a
     * new Feature case later just needs this run again rather than a new
     * migration for a single row.
     */
    public static function ensureDefaults(): void
    {
        $existing = static::query()->pluck('feature')->all();

        $missing = collect(Feature::cases())
            ->reject(fn (Feature $feature) => in_array($feature->value, $existing, true));

        if ($missing->isEmpty()) {
            return;
        }

        static::query()->insert($missing->map(fn (Feature $feature) => [
            'feature' => $feature->value,
            'price_cents' => 2000,
            'trial_days' => Shop::TRIAL_DAYS,
            'created_at' => now(),
            'updated_at' => now(),
        ])->values()->all());
    }
}
