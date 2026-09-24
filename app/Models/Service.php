<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = ['name', 'notes'];

    public function businessPartners(): BelongsToMany
    {
        return $this->belongsToMany(BusinessPartner::class, 'service_business_partner')
            ->withPivot('price')
            ->withTimestamps();
    }
}
