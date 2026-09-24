<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = ['name', 'unit', 'notes', 'material_type_id'];

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
            ->withPivot('price')
            ->withTimestamps();
    }
}
