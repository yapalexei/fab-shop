<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A user's membership in one shop (the shop_user pivot) - what's specific to
 * that user *in that shop*, like their hourly rate and any permission
 * overrides the owner has set for them there.
 */
class ShopMember extends Pivot
{
    protected $table = 'shop_user';

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'permission_overrides' => 'array',
            'trial_ended_dismissed_at' => 'datetime',
        ];
    }
}
