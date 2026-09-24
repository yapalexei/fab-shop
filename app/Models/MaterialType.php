<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of material the shop works with - what a catalog material *is*
 * and what a tool is rated to cut. Built-ins (is_default) come with every
 * shop; custom ones are added per shop, up to MaterialTypes::customLimit().
 */
class MaterialType extends Model
{
    use BelongsToShop;

    protected $fillable = ['name'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
