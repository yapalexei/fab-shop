<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use App\Support\Images;
use Database\Factories\BusinessPartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BusinessPartner extends Model implements HasMedia
{
    /** @use HasFactory<BusinessPartnerFactory> */
    use BelongsToShop, HasFactory, InteractsWithMedia;

    protected $fillable = ['name', 'email', 'phone', 'address', 'notes'];

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
}
