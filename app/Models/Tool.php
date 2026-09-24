<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Consumable tooling billed onto quotes - drill bits, hole saws, cut-off
 * wheels, taps - priced per unit (each, per hole, per foot...) and tagged
 * with the materials it's rated to cut.
 */
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = ['name', 'cost', 'unit', 'notes'];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
        ];
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
