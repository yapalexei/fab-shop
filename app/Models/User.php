<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ShopPermission;
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
        return $this->belongsToMany(Shop::class)
            ->using(ShopMember::class)
            ->withPivot('hourly_rate', 'permission_overrides');
    }

    /**
     * Whether this user may do $permission in their current shop, resolved
     * in order: the Owner role can do everything; then a per-member
     * override the owner set for this user in this shop (allow or deny);
     * then whatever the user's role in this shop has been granted.
     *
     * Role lookups rely on Spatie's team id being the current shop (set per
     * request by SetPermissionsTeamFromCurrentShop).
     */
    public function hasShopPermission(ShopPermission $permission): bool
    {
        if (! $this->current_shop_id) {
            return false;
        }

        if ($this->hasRole('Owner')) {
            return true;
        }

        $overrides = $this->shops()->whereKey($this->current_shop_id)->first()?->pivot->permission_overrides ?? [];

        if (array_key_exists($permission->value, $overrides)) {
            return (bool) $overrides[$permission->value];
        }

        return $this->roles->contains(
            fn ($role) => $role->permissions->contains('name', $permission->value),
        );
    }

    /**
     * Shops this user owns.
     */
    public function ownedShops(): HasMany
    {
        return $this->hasMany(Shop::class, 'owner_id');
    }
}
