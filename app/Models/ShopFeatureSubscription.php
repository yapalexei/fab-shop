<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

/**
 * A shop's standalone purchase of one Feature as an add-on, independent of
 * its plan. Toggled by subscribing/cancelling rather than kept as a full
 * history log - one row per (shop, feature), cancelled_at marking whether
 * it's currently active. Same "declared, not charged" simulation the rest
 * of billing uses until a real payment processor exists (see F0.8 in
 * docs/product-desc.md) - nothing here actually bills a card.
 */
class ShopFeatureSubscription extends Model
{
    use BelongsToShop;

    protected $fillable = ['feature', 'started_at', 'cancelled_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
