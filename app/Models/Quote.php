<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Quote extends Model implements HasMedia
{
    /** @use HasFactory<QuoteFactory> */
    use BelongsToShop, HasFactory, InteractsWithMedia;

    protected $fillable = ['customer_id', 'status', 'notes', 'sent_at', 'emailed_at', 'send_skipped_reason', 'archived_at'];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'sent_at' => 'datetime',
            'emailed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The generated quote PDF - one per quote, replaced (not versioned) each
     * time it's regenerated, since only the latest sent version matters.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')->singleFile();
    }

    public function pdf(): ?Media
    {
        return $this->getFirstMedia('pdf');
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
