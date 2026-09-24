<?php

namespace App\Models;

use App\Enums\CuttableMaterial;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Consumable tooling billed onto quotes - drill bits, hole saws, cut-off
 * wheels, taps - priced per unit (each, per hole, per foot...) and tagged
 * with the materials it's rated to cut.
 */
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = ['name', 'cuts', 'cost', 'unit', 'notes'];

    protected function casts(): array
    {
        return [
            'cuts' => AsEnumCollection::of(CuttableMaterial::class),
            'cost' => 'decimal:2',
        ];
    }

    /**
     * e.g. "Carbon steel, Stainless steel"
     */
    public function cutsLabel(): string
    {
        return $this->cuts->map(fn (CuttableMaterial $material) => $material->label())->implode(', ');
    }
}
