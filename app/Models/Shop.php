<?php

namespace App\Models;

use App\Enums\Feature;
use App\Support\MaterialTypes;
use Carbon\CarbonImmutable;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    public const TRIAL_DAYS = 14;

    protected static function booted(): void
    {
        // Every shop starts with the built-in material types - at signup,
        // in demo data and in test factories alike.
        static::created(fn (Shop $shop) => MaterialTypes::seedDefaults($shop));
    }

    protected $fillable = [
        'name', 'owner_id', 'plan_id', 'trial_started_at', 'trial_ends_at', 'trial_ended_notified_at',
        'timezone', 'stale_quote_days', 'win_rate_window_days', 'job_board_drag_enabled',
        'onboarded_at', 'onboarding_step',
    ];

    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'trial_ended_notified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'suspended_at' => 'datetime',
            'stale_quote_days' => 'integer',
            'win_rate_window_days' => 'integer',
            'job_board_drag_enabled' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ShopMember::class)
            ->withPivot('hourly_rate', 'permission_overrides');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function quickBooksConnection(): HasOne
    {
        return $this->hasOne(QuickBooksConnection::class);
    }

    public function featureSubscriptions(): HasMany
    {
        return $this->hasMany(ShopFeatureSubscription::class);
    }

    /**
     * Where the shop physically is. Timestamps are stored in UTC across the
     * app; anything that depends on the shop's calendar (what "this month"
     * or "today" means, dates shown to people) converts through this.
     * Deliberately *not* used for the trial window, which is fixed UTC
     * instants so a timezone change can't lengthen it.
     */
    public function localTimezone(): string
    {
        return $this->timezone ?? config('shop.defaults.timezone');
    }

    /**
     * The current moment on the shop's wall clock.
     */
    public function localNow(): CarbonImmutable
    {
        return CarbonImmutable::now($this->localTimezone());
    }

    public function staleQuoteDays(): int
    {
        return $this->stale_quote_days ?? config('shop.defaults.stale_quote_days');
    }

    public function winRateWindowDays(): int
    {
        return $this->win_rate_window_days ?? config('shop.defaults.win_rate_window_days');
    }

    /**
     * Whether jobs can be dragged between columns on the board. Off by
     * default - one stray drag on a shared shop-floor tablet shouldn't move
     * a job, so it's something a shop opts into.
     */
    public function jobBoardDragEnabled(): bool
    {
        return $this->job_board_drag_enabled ?? config('shop.defaults.job_board_drag_enabled');
    }

    /**
     * Whether this shop has completed the onboarding wizard. False for a
     * shop mid-wizard (or, transiently, for a verified user with no shop
     * row yet) - the `onboarded` middleware uses this to gate the rest of
     * the app until it's true.
     */
    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

    /**
     * Set only by the platform admin console (never fillable - see the
     * suspended_at migration). Blocks the shop's own users from the app via
     * EnsureShopIsActive without touching any of their data.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * The shop's overall/default trial window - what's shown as "your trial
     * ends <date>" and what NotifyExpiredTrials and the platform admin
     * dashboard key off. Individual features can run on a longer or shorter
     * window than this (see onTrialFor()); this stays the anchor for the
     * default-length case and for anything that isn't feature-specific.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * Whether $feature is still free under the shop's trial - which can run
     * longer or shorter than the shop's overall trial_ends_at if a platform
     * admin gave that specific feature a different trial_days
     * (FeatureSetting::trialDaysFor()). Expressed as an offset from
     * trial_ends_at rather than trial_started_at + trial_days directly, so
     * a feature at the default length (the common case - every feature
     * starts at Shop::TRIAL_DAYS) reduces to exactly onTrial(), and a
     * platform admin manually extending trial_ends_at for one shop shifts
     * every feature's window with it rather than only the default-length
     * ones.
     */
    public function onTrialFor(Feature $feature): bool
    {
        if ($this->trial_ends_at === null) {
            return false;
        }

        $offsetDays = FeatureSetting::trialDaysFor($feature) - self::TRIAL_DAYS;

        return $this->trial_ends_at->copy()->addDays($offsetDays)->isFuture();
    }

    /**
     * True if the feature is in the shop's current plan, the shop bought it
     * standalone as an add-on, or the shop is still within that feature's
     * own trial window.
     */
    public function hasFeature(Feature $feature): bool
    {
        return $this->onTrialFor($feature)
            || ($this->plan?->hasFeature($feature) ?? false)
            || $this->hasActiveFeatureSubscription($feature);
    }

    /**
     * Bypasses the BelongsToShop global scope deliberately: that scope
     * filters by the *authenticated user's* current shop, which is wrong
     * here if $this isn't that shop (e.g. a platform-admin context, or no
     * authenticated user at all in console/queue code) - this checks $this
     * shop's own subscriptions by id, same as any other relation query.
     */
    public function hasActiveFeatureSubscription(Feature $feature): bool
    {
        return $this->featureSubscriptions()
            ->withoutGlobalScopes()
            ->where('feature', $feature->value)
            ->whereNull('cancelled_at')
            ->exists();
    }

    /**
     * @return Collection<int, Feature>
     */
    public function enabledFeatures(): Collection
    {
        $trialDays = FeatureSetting::trialDaysMap();

        $trialFeatures = $this->trial_ends_at === null
            ? collect()
            : collect(Feature::cases())->filter(function (Feature $feature) use ($trialDays) {
                $offsetDays = $trialDays[$feature->value] - self::TRIAL_DAYS;

                return $this->trial_ends_at->copy()->addDays($offsetDays)->isFuture();
            });

        $bundled = $this->plan?->features() ?? collect();

        $addons = $this->featureSubscriptions()
            ->withoutGlobalScopes()
            ->whereNull('cancelled_at')
            ->pluck('feature')
            ->map(fn (string $feature) => Feature::from($feature));

        return $trialFeatures->merge($bundled)->merge($addons)->unique();
    }

    /**
     * Cancels any active add-on subscription for a feature the shop's
     * *current* plan_id already bundles - called after a plan change so a
     * shop is never billed twice for the same feature (once via the plan,
     * once via a standalone add-on it subscribed to before upgrading).
     * Reads plan_id fresh via a query rather than the plan() relation,
     * which may already be loaded (and stale) on $this by the time a
     * caller changes plan_id and calls this.
     *
     * @return int number of add-ons cancelled
     */
    public function cancelSubscriptionsBundledInPlan(): int
    {
        $plan = Plan::query()->find($this->plan_id);

        if ($plan === null) {
            return 0;
        }

        $bundledFeatureValues = $plan->features()->map(fn (Feature $feature) => $feature->value);

        if ($bundledFeatureValues->isEmpty()) {
            return 0;
        }

        return $this->featureSubscriptions()
            ->withoutGlobalScopes()
            ->whereIn('feature', $bundledFeatureValues)
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => now()]);
    }
}
