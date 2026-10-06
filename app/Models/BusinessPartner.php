<?php

namespace App\Models;

use App\Enums\BusinessPartnerType;
use App\Models\Concerns\BelongsToShop;
use App\Models\Concerns\SharesBusinessIdentity;
use App\Support\Images;
use Database\Factories\BusinessPartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BusinessPartner extends Model implements HasMedia
{
    /** @use HasFactory<BusinessPartnerFactory> */
    use BelongsToShop, HasFactory, InteractsWithMedia, SharesBusinessIdentity;

    protected $fillable = ['name', 'email', 'phone', 'address', 'notes', 'type'];

    protected $attributes = ['type' => 'supplier'];

    protected function casts(): array
    {
        return ['type' => BusinessPartnerType::class];
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
     * icon). Eager-load `media` when listing many partners.
     */
    public function logoUrl(): ?string
    {
        return Images::url($this, 'logo', 'business-partners.logo');
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'material_business_partner')
            ->withPivot('price', 'dimension')
            ->withTimestamps();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_business_partner')
            ->withPivot('price')
            ->withTimestamps();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The same business in its customer role, once linked.
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'business_id', 'business_id');
    }

    public function counterpart(): ?Customer
    {
        return Customer::withoutGlobalScopes()->where('business_id', $this->business_id)->first();
    }
}
