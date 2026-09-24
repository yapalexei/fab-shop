<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ShopScope;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Applies tenant scoping to a model. This is the only place that should ever
 * filter a tenant-owned model by shop_id — never add ->where('shop_id', ...)
 * at a call site, since that's the one thing that can quietly diverge and
 * leak across tenants.
 */
trait BelongsToShop
{
    protected static function bootBelongsToShop(): void
    {
        static::addGlobalScope(new ShopScope);

        static::creating(function ($model) {
            if (! $model->shop_id && Auth::check()) {
                $model->shop_id = Auth::user()->current_shop_id;
            }
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
