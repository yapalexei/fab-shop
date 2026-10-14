<?php

namespace App\Models;

use App\Enums\Feature;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One *version* of a subscription tier. `key` names the tier and is shared by
 * every version of it; a shop's `plan_id` points at the exact version it
 * signed up for, so its price, seats and features keep reading from that row
 * even after the tier is edited. Editing a tier (applyEdit()) publishes a new
 * version and stamps the old one `superseded_at` - it is never deleted, since
 * shops may still be on it and it is the record of what the tier used to be.
 *
 * Only the current version of a tier (superseded_at null) can be offered, and
 * only until `expires_on`; see scopeOffered(). Everything that lists plans for
 * a person to pick goes through orderedByTier(), which applies both rules.
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Where the four launch tiers sort when nothing else says (the factory
     * and the seeder use it). The real order is the `position` column, which
     * a platform admin edits.
     */
    public const DEFAULT_ORDER = ['free', 'solo', 'start-up', 'pro'];

    /**
     * The tier that new shops fall back to once a trial ends. It is the
     * floor of the ladder, so it can't be given an expiry or removed.
     */
    public const FALLBACK_KEY = 'free';

    /**
     * Values a shop is entitled to by signing up. Changing any of these (or
     * the feature list) publishes a new version instead of editing in place.
     */
    public const VERSIONED = ['name', 'monthly_price_cents', 'user_limit', 'extra_seat_price_cents'];

    protected $fillable = [
        'key', 'name', 'stripe_price_id', 'user_limit', 'monthly_price_cents', 'extra_seat_price_cents',
        'position', 'expires_on', 'show_expiry', 'apply_to_subscribers', 'subscribers_effective_on',
    ];

    /** Mirrors the column defaults so a just-created row reads the same as a reloaded one. */
    protected $attributes = [
        'position' => 0,
        'version' => 1,
        'show_expiry' => false,
        'apply_to_subscribers' => false,
    ];

    protected function casts(): array
    {
        return [
            'user_limit' => 'integer',
            'monthly_price_cents' => 'integer',
            'extra_seat_price_cents' => 'integer',
            'position' => 'integer',
            'version' => 'integer',
            'superseded_at' => 'datetime',
            'expires_on' => 'date',
            'show_expiry' => 'boolean',
            'apply_to_subscribers' => 'boolean',
            'subscribers_effective_on' => 'date',
        ];
    }

    /**
     * Shops signed up to exactly this version.
     *
     * @return HasMany<Shop, $this>
     */
    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    /** @param  Builder<Plan>  $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('superseded_at');
    }

    /**
     * What a person can pick today: the current version of a tier that
     * hasn't reached its expiry date. Expired and superseded versions stay in
     * the table for the shops still on them.
     *
     * @param  Builder<Plan>  $query
     */
    public function scopeOffered(Builder $query): void
    {
        $query->whereNull('superseded_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_on')->orWhere('expires_on', '>', now()->toDateString()));
    }

    public function isCurrent(): bool
    {
        return $this->superseded_at === null;
    }

    public function isOffered(): bool
    {
        return $this->isCurrent() && ($this->expires_on === null || $this->expires_on->gt(now()->startOfDay()));
    }

    /**
     * Every plan a person can pick, lowest tier first. A plan not yet seeded
     * is simply absent rather than erroring, same as MarketingController's
     * original per-page handling of a fresh install with no plans yet.
     *
     * @return Collection<int, Plan>
     */
    public static function orderedByTier(): Collection
    {
        return static::query()->offered()->orderBy('position')->orderBy('id')->get();
    }

    /** The offered plan for a tier key, if the tier is currently on offer. */
    public static function offeredByKey(string $key): ?Plan
    {
        return static::query()->offered()->where('key', $key)->first();
    }

    /**
     * Where this plan's *tier* sits in the ladder today. An old version keeps
     * the position it was published with, so a comparison ("is that plan
     * higher than mine?") reads the tier's current version instead.
     */
    public function rank(): int
    {
        if ($this->isCurrent()) {
            return $this->position;
        }

        return (int) (static::query()->current()->where('key', $this->key)->value('position') ?? $this->position);
    }

    /**
     * Every version of this tier, newest first.
     *
     * @return Collection<int, Plan>
     */
    public function versions(): Collection
    {
        return static::query()->where('key', $this->key)->orderByDesc('version')->get();
    }

    /**
     * Start a new tier. The key is derived from the name (and kept unique
     * across every tier ever created, retired ones included).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, Feature>  $features
     */
    public static function createTier(array $attributes, array $features): Plan
    {
        return DB::transaction(function () use ($attributes, $features): Plan {
            $plan = static::create([
                ...Arr::only($attributes, [...self::VERSIONED, 'expires_on', 'show_expiry']),
                'key' => static::uniqueKeyFor($attributes['name']),
                'position' => $attributes['position'] ?? ((int) static::query()->max('position') + 1),
            ]);

            $plan->syncFeatures($features);

            return $plan;
        });
    }

    /**
     * Apply an admin's edit to the tier this (current) version belongs to.
     *
     * If anything a shop is entitled to changed - name, price, seats, extra
     * seat price or features - the tier gets a new version and this one is
     * archived, so shops already on it are untouched. If only the ordering
     * and availability settings changed, they are updated in place (they
     * describe the tier, not what a subscriber bought). Returns the version
     * that is current afterwards.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, Feature>  $features
     */
    public function applyEdit(array $attributes, array $features): Plan
    {
        return DB::transaction(function () use ($attributes, $features): Plan {
            $tierSettings = Arr::only($attributes, ['position', 'expires_on', 'show_expiry']);

            if (! $this->entitlementsDiffer($attributes, $features)) {
                $this->update($tierSettings);

                return $this;
            }

            // Archive first: the partial unique index allows one current
            // row per key.
            $this->forceFill(['superseded_at' => now()])->save();

            $next = new static([
                ...Arr::only($attributes, self::VERSIONED),
                ...$tierSettings,
                // What to do about shops already on the tier is decided per
                // published version, never by an in-place edit.
                ...Arr::only($attributes, ['apply_to_subscribers', 'subscribers_effective_on']),
                'key' => $this->key,
                'stripe_price_id' => $this->stripe_price_id,
            ]);
            $next->forceFill(['version' => (int) static::query()->where('key', $this->key)->max('version') + 1])->save();
            $next->syncFeatures($features);

            return $next;
        });
    }

    /** Take the tier off the public list as of today; subscribers stay on it. */
    public function retire(): void
    {
        $this->update(['expires_on' => now()->toDateString()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, Feature>  $features
     */
    private function entitlementsDiffer(array $attributes, array $features): bool
    {
        foreach (self::VERSIONED as $column) {
            if ($this->{$column} !== ($attributes[$column] ?? null)) {
                return true;
            }
        }

        $current = $this->features()->map(fn (Feature $f) => $f->value)->sort()->values()->all();
        $incoming = collect($features)->map(fn (Feature $f) => $f->value)->unique()->sort()->values()->all();

        return $current !== $incoming;
    }

    private static function uniqueKeyFor(string $name): string
    {
        $base = Str::slug($name) ?: 'tier';
        $key = $base;

        for ($n = 2; static::query()->where('key', $key)->exists(); $n++) {
            $key = "{$base}-{$n}";
        }

        return $key;
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
            array_values(array_unique($features, SORT_REGULAR)),
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
