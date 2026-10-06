<?php

namespace App\Models;

use App\Enums\JobStageRole;
use App\Enums\LineItemType;
use App\Enums\MaterialUsageType;
use App\Models\Concerns\BelongsToShop;
use Carbon\CarbonInterface;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Job extends Model implements HasMedia
{
    /** @use HasFactory<JobFactory> */
    use BelongsToShop, HasFactory, InteractsWithMedia, LogsActivity;

    protected $table = 'fabrication_jobs';

    protected $fillable = ['quote_id', 'customer_id', 'job_number', 'job_stage_id', 'assigned_to', 'due_date'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'due_date' => 'date',
            'invoiced_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'quickbooks_synced_at' => 'datetime',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function jobStage(): BelongsTo
    {
        return $this->belongsTo(JobStage::class);
    }

    /**
     * Who's currently working the job - purely informational, shown on the
     * board so the shop can see who has what without asking. Nullable: not
     * every job needs one, and unassigning is just as valid as assigning.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * A job's price is whatever its quote was won at - never re-derived
     * independently, so job cost/pricing can't drift from the quote it
     * came from.
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn (): float => $this->quote->total);
    }

    /**
     * Generate the next job number for the current shop, e.g. "J-0001".
     * Relies on BelongsToShop's global scope for the shop boundary, same as
     * every other tenant-scoped query - never filter by shop_id by hand.
     */
    public static function nextNumber(): string
    {
        return 'J-'.str_pad((string) (static::count() + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * The traveler board's state machine, driven by the shop's own ordered
     * job_stages rather than a fixed enum: any later stage is a legal
     * forward move - skipping stages a job doesn't need is allowed on
     * purpose, not just single-step - and a backward correction is only
     * legal while the job hasn't shipped yet. Cancellation isn't a stage to
     * transition to any more (see cancelled_at/canBeCancelled()); it's a
     * separate side flag, same shape as suspended_at/onboarded_at elsewhere
     * on this app.
     */
    public function canTransitionTo(JobStage $target): bool
    {
        if ($this->isCancelled() || $this->jobStage->role === JobStageRole::Invoiced) {
            return false;
        }

        if ($target->position > $this->jobStage->position) {
            return true;
        }

        // Once shipped, only cancellation remains legal - no backward moves.
        return $this->jobStage->role !== JobStageRole::Shipped
            && $target->position < $this->jobStage->position;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Past its due date and still not shipped. $today is the shop's local
     * date (Shop::localNow()) - due_date is a plain calendar day, so it's
     * compared against the shop's "today", never UTC's.
     */
    public function isOverdue(CarbonInterface $today): bool
    {
        return $this->due_date !== null
            && ! $this->isCancelled()
            && $this->finished_at === null
            && $this->due_date->toDateString() < $today->toDateString();
    }

    public function canBeCancelled(): bool
    {
        return ! $this->isCancelled();
    }

    /**
     * @return Collection<int, JobStage>
     */
    public function availableTransitions(): Collection
    {
        return $this->shopJobStages()->filter(fn (JobStage $target) => $this->canTransitionTo($target))->values();
    }

    /**
     * Every stage configured for this job's shop, in position order.
     *
     * @return Collection<int, JobStage>
     */
    public function shopJobStages(): Collection
    {
        return JobStage::query()->where('shop_id', $this->shop_id)->orderBy('position')->get();
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Transitions are logged manually with a human-readable message
        // (see JobStatusController) - this only grants the `activities()`
        // relation without Eloquent's own generic attribute-diff logging.
        return LogOptions::defaults()->logOnly([])->dontLogEmptyChanges();
    }

    /**
     * Progress photos taken on the shop floor - multiple per job, newest
     * first for display, with a resized thumbnail so the board/detail page
     * doesn't ship full-resolution phone photos just to show a preview.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Generated synchronously (medialibrary queues conversions by
        // default) - a photo needs its thumbnail to exist by the time the
        // upload's response reaches the browser, not whenever a queue
        // worker next runs.
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->nonQueued()
            ->performOnCollections('photos');
    }

    public function photos(): Collection
    {
        return $this->getMedia('photos')->sortByDesc('created_at')->values();
    }

    /**
     * The job's material-usage ledger - actual consumption/waste, kept
     * entirely separate from the quote's line items (the estimate).
     */
    public function materialUsages(): HasMany
    {
        return $this->hasMany(JobMaterialUsage::class);
    }

    /**
     * The job's qualification ledger - who performed which procedure and
     * whether they were certified for it at the time.
     */
    public function qualificationRecords(): HasMany
    {
        return $this->hasMany(JobQualificationRecord::class);
    }

    /**
     * What the quote estimated material would cost the shop - quantity x
     * unit_cost on the quote's material lines, before markup, since that's
     * the shop's own cost and the correct basis to compare actual cost
     * against (not what the customer is charged).
     */
    protected function estimatedMaterialCost(): Attribute
    {
        return Attribute::get(fn (): float => $this->quote->lineItems
            ->where('type', LineItemType::Material)
            ->sum->cost);
    }

    protected function actualMaterialCost(): Attribute
    {
        return Attribute::get(fn (): float => $this->materialUsages
            ->where('type', MaterialUsageType::Used)
            ->sum->cost);
    }

    protected function scrapCost(): Attribute
    {
        return Attribute::get(fn (): float => $this->materialUsages
            ->where('type', MaterialUsageType::Scrap)
            ->sum->cost);
    }

    /**
     * Actual + scrap cost against what was estimated - positive means the
     * job cost more material than quoted, eating into its margin.
     */
    protected function materialCostVariance(): Attribute
    {
        return Attribute::get(fn (): float => $this->actual_material_cost + $this->scrap_cost - $this->estimated_material_cost);
    }
}
