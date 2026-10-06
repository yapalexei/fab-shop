<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use App\Support\Images;
use App\Support\ToolModels;
use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Consumable tooling billed onto quotes - drill bits, hole saws, cut-off
 * wheels, taps - priced per unit (each, per hole, per foot...) and tagged
 * with the materials it's rated to cut.
 */
class Tool extends Model implements HasMedia
{
    /** @use HasFactory<ToolFactory> */
    use BelongsToShop, HasFactory, InteractsWithMedia;

    protected $fillable = ['name', 'cost', 'unit', 'notes'];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        Images::collection($this, 'image');
        $this->addMediaCollection(ToolModels::COLLECTION)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        Images::conversion($this, 'image');
    }

    /**
     * The photo's URL (a square crop), or null when none is uploaded.
     * Eager-load `media` when listing many tools.
     */
    public function imageUrl(): ?string
    {
        return Images::url($this, 'image', 'tools.image');
    }

    /**
     * The material types this tool is rated to cut.
     */
    public function materialTypes(): BelongsToMany
    {
        return $this->belongsToMany(MaterialType::class)->orderBy('name');
    }

    /**
     * e.g. "Carbon steel, Stainless steel"
     */
    public function cutsLabel(): string
    {
        return $this->materialTypes->pluck('name')->implode(', ');
    }
}
