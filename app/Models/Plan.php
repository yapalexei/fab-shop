<?php

namespace App\Models;

use App\Enums\Feature;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Display order for anywhere a shop or platform admin picks from every
     * tier (onboarding, the marketing page, the platform admin override).
     * Deliberately not `orderBy('id')` - a tier added after the others
     * already exist in a real database (like Solo was) gets whatever id is
     * next, which has no relation to where it should show up size-wise.
     */
    public const TIER_ORDER = ['free', 'solo', 'start-up', 'pro'];

    protected $fillable = ['key', 'name', 'stripe_price_id', 'user_limit', 'monthly_price_cents'];

    /**
     * Every plan that exists, in TIER_ORDER - a plan not yet seeded is
     * simply absent rather than erroring, same as MarketingController's
     * original per-page handling of a fresh install with no plans yet.
     *
     * @return Collection<int, Plan>
     */
    public static function orderedByTier(): Collection
    {
        $plans = static::query()->get()->keyBy('key');

        return collect(self::TIER_ORDER)
            ->filter(fn (string $key) => $plans->has($key))
            ->map(fn (string $key) => $plans[$key])
            ->values();
    }

    /**
     * Attach a set of features to this plan (replacing whatever it had).
     *
     * @param  array<int, Feature>  $features
     */
    public function syncFeatures(array $features): void
    {
        DB::table('plan_feature')->where('plan_id', $this->id)->delete();

        DB::table('plan_feature')->insert(array_map(
            fn (Feature $feature): array => ['plan_id' => $this->id, 'feature' => $feature->value],
            $features,
        ));
    }

    /**
     * @return Collection<int, Feature>
     */
    public function features(): Collection
    {
        return DB::table('plan_feature')
            ->where('plan_id', $this->id)
            ->pluck('feature')
            ->map(fn (string $feature): Feature => Feature::from($feature));
    }

    public function hasFeature(Feature $feature): bool
    {
        return DB::table('plan_feature')
            ->where('plan_id', $this->id)
            ->where('feature', $feature->value)
            ->exists();
    }
}
