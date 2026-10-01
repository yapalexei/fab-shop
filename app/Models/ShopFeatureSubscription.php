<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A shop's standalone purchase of one Feature as an add-on, independent of
 * its plan. Toggled by subscribing/cancelling rather than kept as a full
 * history log - one row per (shop, feature), cancelled_at/ends_at marking whether
 * it's currently active (a cancelled one stays active until ends_at). Same "declared, not charged" simulation the rest
 * of billing uses until a real payment processor exists (see F0.8 in
 * docs/product-desc.md) - nothing here actually bills a card.
 */
class ShopFeatureSubscription extends Model
{
    use BelongsToShop;

    protected $fillable = ['feature', 'started_at', 'cancelled_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Still giving access: never cancelled, or cancelled but paid through
     * a date that hasn't passed yet.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('cancelled_at')
            ->orWhere('ends_at', '>', now()));
    }

    /** Cancelled, but still active until `ends_at`. */
    public function isEnding(): bool
    {
        return $this->cancelled_at !== null && $this->ends_at?->isFuture() === true;
    }
}
