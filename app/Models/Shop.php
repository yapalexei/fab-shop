<?php

namespace App\Models;

use App\Enums\Feature;
use Carbon\CarbonImmutable;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    public const TRIAL_DAYS = 14;

    protected $fillable = [
        'name', 'owner_id', 'plan_id', 'trial_started_at', 'trial_ends_at', 'trial_ended_notified_at',
        'timezone', 'stale_quote_days', 'win_rate_window_days', 'job_board_drag_enabled',
    ];

    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'trial_ended_notified_at' => 'datetime',
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

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * True if the feature is in the shop's current plan, or the shop is still
     * on trial (trial shops get every feature during the trial window).
     */
    public function hasFeature(Feature $feature): bool
    {
        return $this->onTrial() || ($this->plan?->hasFeature($feature) ?? false);
    }

    /**
     * @return Collection<int, Feature>
     */
    public function enabledFeatures(): Collection
    {
        if ($this->onTrial()) {
            return collect(Feature::cases());
        }

        return $this->plan?->features() ?? collect();
    }
}
