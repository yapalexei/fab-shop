<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use App\Support\Images;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Material extends Model implements HasMedia
{
    /** @use HasFactory<MaterialFactory> */
    use BelongsToShop, HasFactory, InteractsWithMedia;

    protected $fillable = ['name', 'unit', 'notes', 'material_type_id'];

    public function registerMediaCollections(): void
    {
        Images::collection($this, 'image');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        Images::conversion($this, 'image');
    }

    /**
     * The photo's URL (a square crop), or null when none is uploaded.
     * Eager-load `media` when listing many materials.
     */
    public function imageUrl(): ?string
    {
        return Images::url($this, 'image', 'materials.image');
    }

    /**
     * What this material is (e.g. "1/4in Steel Plate" is Carbon steel) - lets
     * the quote builder check a quote's tooling is rated for it.
     */
    public function materialType(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class);
    }

    public function businessPartners(): BelongsToMany
    {
        return $this->belongsToMany(BusinessPartner::class, 'material_business_partner')
            ->withPivot('price', 'dimension')
            ->withTimestamps();
    }
}
