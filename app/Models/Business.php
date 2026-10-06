<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An outside company, independent of why the shop deals with it. A
 * Customer and a BusinessPartner are roles it can hold - both, either, or
 * (until the first link is made) neither has a Business row at all.
 */
class Business extends Model
{
    use BelongsToShop;

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function partner(): HasOne
    {
        return $this->hasOne(BusinessPartner::class);
    }
}
