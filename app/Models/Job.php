<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\LineItemType;
use App\Enums\MaterialUsageType;
use App\Models\Concerns\BelongsToShop;
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

    protected $fillable = ['quote_id', 'customer_id', 'job_number', 'status'];

    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'invoiced_at' => 'datetime',
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
     * The traveler board's state machine: single-step forward is always
     * legal (the normal path through every station), a job can be cancelled
     * from anywhere it isn't already cancelled, and a backward correction is
     * only legal while the job is still "in hand" on the shop floor -
     * Finishing/Shipped/Invoiced can't be walked back, only cancelled.
     */
    public function canTransitionTo(JobStatus $target): bool
    {
        if ($target === JobStatus::Cancelled) {
            return $this->status !== JobStatus::Cancelled;
        }

        if ($this->status === JobStatus::Cancelled || $this->status === JobStatus::Invoiced) {
            return false;
        }

        $currentOrder = $this->status->order();
        $targetOrder = $target->order();

        if ($targetOrder === $currentOrder + 1) {
            return true;
        }

        return $targetOrder < $currentOrder && $this->status->isInShop();
    }

    /**
     * @return array<int, JobStatus>
     */
    public function availableTransitions(): array
    {
        return array_values(array_filter(
            JobStatus::cases(),
            fn (JobStatus $target) => $this->canTransitionTo($target),
        ));
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
