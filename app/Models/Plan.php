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

    protected $fillable = ['key', 'name', 'stripe_price_id', 'user_limit', 'monthly_price_cents'];

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
