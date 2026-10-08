<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use App\Models\Concerns\NeedsAiReview;
use App\Models\Concerns\SharesBusinessIdentity;
use App\Support\Images;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Customer extends Model implements HasMedia
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToShop, HasFactory, InteractsWithMedia, NeedsAiReview, SharesBusinessIdentity;

    protected $fillable = ['name', 'email', 'phone', 'address', 'notes'];

    protected function casts(): array
    {
        return ['ai_touched_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        Images::collection($this, 'logo');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        Images::conversion($this, 'logo');
    }

    /**
     * The logo's URL, or null when none is uploaded (the UI shows a building
     * icon). Eager-load `media` when listing many customers.
     */
    public function logoUrl(): ?string
    {
        return Images::url($this, 'logo', 'customers.logo');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The same business in its partner role, once linked.
     */
    public function businessPartner(): HasOne
    {
        return $this->hasOne(BusinessPartner::class, 'business_id', 'business_id');
    }

    public function counterpart(): ?BusinessPartner
    {
        return BusinessPartner::withoutGlobalScopes()->where('business_id', $this->business_id)->first();
    }
}
