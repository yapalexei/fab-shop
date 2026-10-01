<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\BusinessPartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BusinessPartner extends Model
{
    /** @use HasFactory<BusinessPartnerFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'address', 'notes'];

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
