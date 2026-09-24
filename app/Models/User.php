<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

// current_shop_id is deliberately not fillable - mass-assigning it would let
// a request switch a user into a shop they aren't a member of. Set it via
// direct property assignment + save() after checking membership, not update().
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The shop this user is currently acting within. Tenant scoping and
     * permission checks are relative to this, not to shop membership as a whole.
     */
    public function currentShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'current_shop_id');
    }

    /**
     * Every shop this user is a member of.
     */
    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class)->withPivot('hourly_rate');
    }

    /**
     * Shops this user owns.
     */
    public function ownedShops(): HasMany
    {
        return $this->hasMany(Shop::class, 'owner_id');
    }
}
