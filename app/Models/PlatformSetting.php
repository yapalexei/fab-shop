<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A single global row of app-wide settings - not per-shop (Shop's own
 * nullable columns already cover that) and not per-feature (FeatureSetting
 * already covers that). Starts with just the default trial length new shops
 * get; current() lazily creates the row, same "don't need a seeder for one
 * row" reasoning as FeatureSetting::ensureDefaults().
 */
class PlatformSetting extends Model
{
    // What a brand-new install advertises and grants. 30 days, not the
    // historical Shop::TRIAL_DAYS (14): a fab shop's quote-to-job-to-invoice
    // cycle takes weeks, so a shorter trial ends before a job finishes.
    public const DEFAULT_TRIAL_DAYS = 30;

    private const CACHE_KEY = 'platform-setting.default-trial-days';

    // The two-factor policy columns are written only by the platform admin
    // security page (forceFill), together with their set-at timestamp.
    protected $fillable = ['default_trial_days'];

    protected function casts(): array
    {
        return [
            'default_trial_days' => 'integer',
            'two_factor_required_roles' => 'array',
            'two_factor_grace_days' => 'integer',
            'two_factor_policy_set_at' => 'datetime',
        ];
    }

    /**
     * The one row - created with today's hardcoded default the first time
     * anything asks for it, so a fresh install works without a seeder.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['default_trial_days' => self::DEFAULT_TRIAL_DAYS]);
    }

    /**
     * How many days a brand-new shop's trial lasts, and what the marketing
     * page advertises - the same number, on purpose, so marketing copy can
     * never promise a trial length longer or shorter than what a real
     * signup actually gets (see MarketingController).
     *
     * Cached indefinitely - the marketing page and every signup read this,
     * but it only ever changes when a platform admin edits it, so it's not
     * worth a database round trip on every public page load. See
     * forgetCache(), which the admin form calls right after writing a new
     * value, for why a stale cached value never lingers past that edit.
     *
     * Existing shops are unaffected by changes here - see
     * Shop::trialBaselineDays() for why their own feature-trial-offset math
     * keeps using whatever this value was at their own signup, not today's.
     */
    public static function defaultTrialDays(): int
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::current()->default_trial_days,
        );
    }

    /**
     * Call immediately after writing a new value - same synchronous,
     * right-after-the-write placement as Spatie's own
     * forgetCachedPermissions(), so the change is visible on the very next
     * request instead of needing a manual cache:clear.
     */
    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
