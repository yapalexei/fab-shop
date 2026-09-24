<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = ['name', 'unit', 'notes'];

    public function businessPartners(): BelongsToMany
    {
        return $this->belongsToMany(BusinessPartner::class, 'material_business_partner')
            ->withPivot('price')
            ->withTimestamps();
    }
}
