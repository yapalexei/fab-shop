<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Models\Concerns\BelongsToShop;
use App\Models\Concerns\NeedsAiReview;
use App\Support\Attachments;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Quote extends Model implements HasMedia
{
    /** @use HasFactory<QuoteFactory> */
    use BelongsToShop, HasFactory, InteractsWithMedia, NeedsAiReview;

    protected $fillable = ['customer_id', 'status', 'notes', 'due_date', 'sent_at', 'emailed_at', 'email_failed_at', 'send_skipped_reason', 'archived_at'];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'emailed_at' => 'datetime',
            'email_failed_at' => 'datetime',
            'archived_at' => 'datetime',
            'ai_touched_at' => 'datetime',
        ];
    }

    /**
     * The generated quote PDF - one per quote, replaced (not versioned) each
     * time it's regenerated, since only the latest sent version matters.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')->singleFile();
        $this->addMediaCollection('attachments');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        Attachments::registerThumbnail($this, $media);
    }

    /**
     * Blueprints, reference photos and other files uploaded against the
     * quote - copied onto the job if the quote is converted.
     *
     * @return Collection<int, Media>
     */
    public function attachments(): Collection
    {
        return $this->getMedia('attachments')->sortBy('created_at')->values();
    }

    public function pdf(): ?Media
    {
        return $this->getFirstMedia('pdf');
    }

    /**
     * Sent, not archived, and no decision for the shop's stale-quote window -
     * the same definition the dashboard flags for a follow-up call.
     *
     * @param  Builder<Quote>  $query
     */
    public function scopeStale(Builder $query, Shop $shop): void
    {
        $query->where('status', QuoteStatus::Sent)
            ->whereNull('archived_at')
            ->where('sent_at', '<=', now()->subDays($shop->staleQuoteDays()));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class)->orderBy('sort_order');
    }

    public function historyEntries(): HasMany
    {
        return $this->hasMany(QuoteHistoryEntry::class)->latest('id');
    }

    public function job(): HasOne
    {
        return $this->hasOne(Job::class);
    }

    /**
     * Once the quote has gone to the customer by email, who it went to is
     * part of the record - it can only be corrected if that email failed to
     * deliver. (A draft, or one marked sent without emailing, has no email.)
     */
    protected function customerLocked(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status !== QuoteStatus::Draft
            && $this->send_skipped_reason === null
            && $this->email_failed_at === null);
    }

    protected function isArchived(): Attribute
    {
        return Attribute::get(fn (): bool => $this->archived_at !== null);
    }

    /**
     * Total cost (quantity x unit_cost) across all lines, before markup.
     */
    protected function cost(): Attribute
    {
        return Attribute::get(fn (): float => $this->lineItems->sum->cost);
    }

    /**
     * What the customer is actually charged - sum of each line's total
     * (its manual override if set, otherwise cost + markup).
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn (): float => $this->lineItems->sum->total);
    }

    protected function margin(): Attribute
    {
        return Attribute::get(fn (): float => $this->total - $this->cost);
    }

    protected function marginPercent(): Attribute
    {
        return Attribute::get(fn (): float => $this->total > 0 ? ($this->margin / $this->total) * 100 : 0.0);
    }
}
