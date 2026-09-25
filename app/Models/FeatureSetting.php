<?php

namespace App\Models;

use App\Enums\Feature;
use Illuminate\Database\Eloquent\Model;

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
