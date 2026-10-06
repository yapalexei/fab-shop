<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use App\Support\Images;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Customer extends Model implements HasMedia
{
    /** @use HasFactory<CustomerFactory> */
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
     * icon). Eager-load `media` when listing many customers.
     */
    public function logoUrl(): ?string
    {
        return Images::url($this, 'logo', 'customers.logo');
    }
}
