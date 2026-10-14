<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Notifications\PlanUpdated;
use App\Support\JobStages;
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

    // The historical default, kept as the fallback for shops.trial_baseline_days
    // (every shop created before that column existed was, in fact, created
    // under this exact value) and as PlatformSetting's own seed default.
    // No longer read directly for gating math - see trialBaselineDays().
    public const TRIAL_DAYS = 14;

    /**
     * Set only by moveToPlan(): a plan change that is carrying the shop's
     * entitlements over rather than replacing them.
     */
    private bool $carryingOverPlan = false;

    protected static function booted(): void
    {
        // Choosing a different plan (the shop itself, or a platform admin)
        // starts from that plan: a scheduled price move and any features
        // carried over from a previous version of the old plan no longer apply.
        static::saving(function (Shop $shop) {
            if ($shop->exists && $shop->isDirty('plan_id') && ! $shop->carryingOverPlan) {
                $shop->pending_plan_id = null;
                $shop->pending_plan_effective_at = null;
                $shop->extra_features = null;
                $shop->plan_notice = null;
            }
        });

        // Every shop starts with the built-in material types and job board
        // pipeline - at signup, in demo data and in test factories alike.
        static::created(function (Shop $shop) {
            MaterialTypes::seedDefaults($shop);
            JobStages::seedDefaults($shop);
        });
    }

    protected $fillable = [
        'name', 'owner_id', 'plan_id', 'seat_limit_override', 'extra_seats', 'trial_started_at', 'trial_ends_at', 'trial_ended_notified_at',
        'trial_baseline_days', 'subscription_renews_at', 'billing_interval', 'timezone', 'stale_quote_days', 'win_rate_window_days',
        'job_board_drag_enabled', 'quote_emailing_enabled', 'onboarded_at', 'onboarding_step',
    ];

    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'trial_ended_notified_at' => 'datetime',
            'subscription_renews_at' => 'datetime',
            'pending_plan_effective_at' => 'datetime',
            'extra_features' => 'array',
            'plan_notice' => 'array',
            'notified_discontinued_features' => 'array',
            'billing_interval' => BillingInterval::class,
            'onboarded_at' => 'datetime',
            'suspended_at' => 'datetime',
            'two_factor_required_roles' => 'array',
            'two_factor_grace_days' => 'integer',
            'two_factor_policy_set_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'deletion_scheduled_for' => 'datetime',
            'stale_quote_days' => 'integer',
            'win_rate_window_days' => 'integer',
            'job_board_drag_enabled' => 'boolean',
            'quote_emailing_enabled' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The shop's people. Deliberately excludes AI agent identities (see
     * agents()): they aren't team members - no seat, no row on the Team page,
     * not pickable as an employee or job assignee.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ShopMember::class)
            ->withPivot('hourly_rate', 'permission_overrides', 'trial_ended_dismissed_at')
            // Records when someone joined - the two-factor grace period for a
            // new teammate counts from here (TwoFactorPolicy).
            ->withTimestamps()
            ->where('users.is_agent', false);
    }

    /**
     * The identity the shop's own AI connects as over MCP - a user row that
     * belongs to this one shop only, with access granted purely through
     * permission overrides on its membership (no role).
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ShopMember::class)
            ->withPivot('permission_overrides')
            ->withTimestamps()
            ->where('users.is_agent', true);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** The plan the shop moves to when its current paid period ends, if a price drop is scheduled. */
    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    /**
     * Features the shop holds on top of its plan row (see the migration that
     * added `extra_features`).
     *
     * @return Collection<int, Feature>
     */
    public function extraFeatures(): Collection
    {
        return collect($this->extra_features ?? [])
            ->map(fn (string $feature) => Feature::tryFrom($feature))
            ->filter()
            ->values();
    }

    /**
     * Everything the plan gives this shop: its plan row's features plus the
     * ones it keeps or was given beyond that row.
     *
     * @return Collection<int, Feature>
     */
    public function planFeatures(): Collection
    {
        return ($this->plan?->features() ?? collect())
            ->merge($this->extraFeatures())
            ->reject(fn (Feature $feature) => $feature->isDiscontinued())
            ->unique()
            ->values();
    }

    public function hasPlanFeature(Feature $feature): bool
    {
        return ! $feature->isDiscontinued()
            && (($this->plan?->hasFeature($feature) ?? false) || $this->extraFeatures()->contains($feature));
    }

    /**
     * Record a change to what the shop's plan gives it and tell its owner:
     * what was added, what was retired for good, a lower price and any seat
     * change. The summary is kept (merged with an earlier one the owner hasn't
     * dismissed) for the Plan page; the email carries only this change.
     *
     * @param  array{plan_name: string, added?: list<string>, retired?: list<string>, price?: array{from_cents: int, to_cents: int, effective_at: string}|null, seats?: array{from: int|null, to: int|null}|null}  $changes
     */
    public function announcePlanChange(array $changes): void
    {
        $changes += ['added' => [], 'retired' => [], 'price' => null, 'seats' => null];
        $previous = $this->plan_notice ?? [];

        $retired = array_values(array_unique([...($previous['retired'] ?? []), ...$changes['retired']]));
        $added = array_values(array_diff(array_unique([...($previous['added'] ?? []), ...$changes['added']]), $retired));

        $this->forceFill(['plan_notice' => [
            'at' => now()->toIso8601String(),
            'plan_name' => $changes['plan_name'],
            'added' => $added,
            'retired' => $retired,
            'price' => $changes['price'] ?? ($previous['price'] ?? null),
            'seats' => $changes['seats'] ?? ($previous['seats'] ?? null),
        ]])->save();

        $this->owner?->notify(new PlanUpdated($this, $changes));
    }

    /**
     * Move the shop onto $plan (a lower price for the same tier), keeping
     * every feature it has today: whatever $plan doesn't include is carried
     * over as extra features, and anything $plan adds comes with it. Clears a
     * scheduled move, and cancels add-ons the plan now bundles.
     */
    public function moveToPlan(Plan $plan): void
    {
        $carried = $this->planFeatures()
            ->reject(fn (Feature $feature) => $plan->hasFeature($feature))
            ->map(fn (Feature $feature) => $feature->value)
            ->values()
            ->all();

        $this->carryingOverPlan = true;

        try {
            $this->forceFill([
                'plan_id' => $plan->id,
                'pending_plan_id' => null,
                'pending_plan_effective_at' => null,
                'extra_features' => $carried === [] ? null : $carried,
            ])->save();
        } finally {
            $this->carryingOverPlan = false;
        }

        $this->unsetRelation('plan');
        $this->cancelSubscriptionsBundledInPlan();
    }

    public function quickBooksConnection(): HasOne
    {
        return $this->hasOne(QuickBooksConnection::class);
    }

    public function aiSetting(): HasOne
    {
        return $this->hasOne(ShopAiSetting::class);
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
     * How many seats this shop has paid for - a platform-admin override if
     * one's set, otherwise the plan's own user_limit plus any extra seats
     * bought. Null means unlimited (Plan::user_limit's own convention).
     */
    public function seatLimit(): ?int
    {
        if ($this->seat_limit_override !== null) {
            return $this->seat_limit_override;
        }

        $included = $this->plan?->user_limit;

        return $included === null ? null : $included + $this->extra_seats;
    }

    /**
     * The limit actually enforced when adding people. During the trial a
     * shop gets the top plan's seats (the trial grants every feature, so a
     * crew should be able to try the product together); an admin override
     * still wins.
     */
    public function seatCapacity(): ?int
    {
        $limit = $this->seatLimit();

        if ($limit === null || $this->seat_limit_override !== null || ! $this->onTrial()) {
            return $limit;
        }

        $top = Plan::orderedByTier()->last();

        if ($top === null) {
            return $limit;
        }

        return $top->user_limit === null ? null : max($limit, $top->user_limit);
    }

    /** People who hold a seat today (agents never do - see members()). */
    public function seatsUsed(): int
    {
        return $this->members()->count();
    }

    /**
     * Whether $additional more people fit, counting unexpired, unaccepted
     * invitations as taken (so a shop can't invite past its limit and only
     * find out when people accept). $exceptEmail is an invite being
     * refreshed, which already holds its seat.
     */
    public function hasRoomForSeats(int $additional = 1, ?string $exceptEmail = null): bool
    {
        $capacity = $this->seatCapacity();

        if ($capacity === null) {
            return true;
        }

        $pending = Invitation::query()
            ->where('shop_id', $this->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->when($exceptEmail !== null, fn ($query) => $query->where('email', '!=', $exceptEmail))
            ->count();

        return $this->seatsUsed() + $pending + $additional <= $capacity;
    }

    /** The message shown when a seat can't be added. */
    public function seatLimitMessage(): string
    {
        $capacity = $this->seatCapacity();
        $buyable = $this->plan?->extra_seat_price_cents !== null;

        return "This shop's {$capacity} seat".($capacity === 1 ? ' is' : 's are').' all taken.'
            .($buyable ? ' Add seats on the Plan page.' : ' Upgrade your plan for more seats.');
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
     * Whether quotes can be emailed to customers: the shop turned it on in
     * its settings (off by default - plenty of shops hand quotes over
     * themselves) *and* its plan includes Feature::QuoteEmailing. The setting
     * is kept if the plan lapses, but nothing is sent until it's included
     * again.
     */
    public function quoteEmailingEnabled(): bool
    {
        return $this->quote_emailing_enabled && $this->hasFeature(Feature::QuoteEmailing);
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
     * Whether this shop has ever paid for anything: a priced plan, a
     * "paid through" date, or any add-on (cancelled ones included - they
     * still prove it). Derived from existing data rather than stored, so it
     * can't drift. Used to stop nagging about an ended trial a shop has
     * clearly moved past.
     */
    public function hasEverSubscribed(): bool
    {
        return $this->subscription_renews_at !== null
            || ($this->plan?->monthly_price_cents ?? 0) > 0
            || $this->featureSubscriptions()->withoutGlobalScopes()->exists();
    }

    /**
     * Whether $user should still see the "your trial ended" notice: the trial
     * is over, the shop never subscribed, and they haven't dismissed it since
     * the trial ended (an extended-then-expired trial brings it back).
     */
    public function showsTrialEndedNoticeTo(User $user): bool
    {
        if ($this->trial_ends_at === null || $this->onTrial() || $this->hasEverSubscribed()) {
            return false;
        }

        $dismissedAt = $user->shops()->whereKey($this->id)->first()?->pivot->trial_ended_dismissed_at;

        return $dismissedAt === null || $dismissedAt->lt($this->trial_ends_at);
    }

    /** The cadence the plan and add-ons renew on (monthly until the shop picks annual). */
    public function billingInterval(): BillingInterval
    {
        return $this->billing_interval ?? BillingInterval::Monthly;
    }

    /**
     * subscription_renews_at is a platform admin's own manually-set "paid
     * through" date, not something a real payment processor derives - no
     * Stripe/Cashier integration exists yet (see the migration's own note).
     * Null means "never tracked," which is distinct from overdue: a shop
     * nobody has ever marked paid isn't necessarily delinquent, it just has
     * no record either way (e.g. still on trial, or on the Free plan).
     */
    public function isSubscriptionOverdue(): bool
    {
        return $this->subscription_renews_at !== null && $this->subscription_renews_at->isPast();
    }

    /**
     * What the platform-wide default trial length actually was when this
     * shop signed up (nullable - shops that predate this column were, in
     * fact, all created under the old hardcoded Shop::TRIAL_DAYS, so that's
     * an exact fallback, not a guess). This is the baseline onTrialFor()/
     * enabledFeatures() offset against - never PlatformSetting's *current*
     * value, which a platform admin can change at any time and which would
     * otherwise silently throw off every existing shop's per-feature trial
     * math the moment it did.
     */
    public function trialBaselineDays(): int
    {
        return $this->trial_baseline_days ?? self::TRIAL_DAYS;
    }

    /**
     * Whether $feature is still free under the shop's trial - which can run
     * longer or shorter than the shop's overall trial_ends_at if a platform
     * admin gave that specific feature a different trial_days
     * (FeatureSetting::trialDaysFor()). Expressed as an offset from
     * trial_ends_at rather than trial_started_at + trial_days directly, so
     * a feature at the default length (the common case - every feature
     * starts at this shop's own trialBaselineDays()) reduces to exactly
     * onTrial(), and a platform admin manually extending trial_ends_at for
     * one shop shifts every feature's window with it rather than only the
     * default-length ones.
     */
    public function onTrialFor(Feature $feature): bool
    {
        if ($this->trial_ends_at === null) {
            return false;
        }

        $offsetDays = FeatureSetting::trialDaysFor($feature) - $this->trialBaselineDays();

        return $this->trial_ends_at->copy()->addDays($offsetDays)->isFuture();
    }

    /**
     * True if the feature is in the shop's current plan, the shop bought it
     * standalone as an add-on, or the shop is still within that feature's
     * own trial window.
     */
    public function hasFeature(Feature $feature): bool
    {
        if ($feature->isDiscontinued()) {
            return false;
        }

        return $this->onTrialFor($feature)
            || $this->hasPlanFeature($feature)
            || $this->hasActiveFeatureSubscription($feature);
    }

    /**
     * Whether the shop may still *see* $feature's pages and data without
     * being entitled to use it. Any shop that ever had a trial keeps
     * read-only access to what it built during it (a trial that ran out, or
     * a plan without the feature, must not hide the shop's own records) -
     * writes still require hasFeature().
     */
    public function canViewFeature(Feature $feature): bool
    {
        return $this->hasFeature($feature) || $this->trial_ends_at !== null;
    }

    /**
     * Features the shop can view but not use - drives the "read-only" UI.
     *
     * @return Collection<int, Feature>
     */
    public function readOnlyFeatures(): Collection
    {
        if ($this->trial_ends_at === null) {
            return collect();
        }

        $enabled = $this->enabledFeatures();

        return collect(Feature::cases())
            ->filter(fn (Feature $feature) => $feature->isShipped() && ! $enabled->contains($feature))
            ->values();
    }

    /**
     * Whether a higher tier than the shop's current plan exists. A shop with
     * no plan counts as below every tier.
     */
    public function canUpgrade(): bool
    {
        $rank = $this->plan?->rank();

        return Plan::orderedByTier()->contains(fn (Plan $plan) => $rank === null || $plan->position > $rank);
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
            ->active()
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
                $offsetDays = $trialDays[$feature->value] - $this->trialBaselineDays();

                return $this->trial_ends_at->copy()->addDays($offsetDays)->isFuture();
            });

        $bundled = $this->planFeatures();

        $addons = $this->featureSubscriptions()
            ->withoutGlobalScopes()
            ->active()
            ->pluck('feature')
            ->map(fn (string $feature) => Feature::from($feature));

        return $trialFeatures->merge($bundled)->merge($addons)
            ->reject(fn (Feature $feature) => $feature->isDiscontinued())
            ->unique();
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

        $bundledFeatureValues = $plan->features()->merge($this->extraFeatures())->unique()->map(fn (Feature $feature) => $feature->value)->values();

        if ($bundledFeatureValues->isEmpty()) {
            return 0;
        }

        return $this->featureSubscriptions()
            ->withoutGlobalScopes()
            ->whereIn('feature', $bundledFeatureValues)
            ->active()
            ->update(['cancelled_at' => now(), 'ends_at' => now()]);
    }
}
