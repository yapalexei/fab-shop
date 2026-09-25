<?php

namespace App\Models;

use App\Support\ShopPermissions;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
//
// implements MustVerifyEmail: Authenticatable already provides the trait's
// hasVerifiedEmail()/markEmailAsVerified() methods unconditionally - this
// interface is the only thing the framework's `verified` middleware and
// EmailVerificationPromptController actually check for.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
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
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * A platform-level staff flag, entirely separate from any per-shop role -
     * granted out-of-band (tinker, or the app:grant-platform-admin command),
     * never through a user-facing form, since it crosses tenant boundaries
     * the rest of the app is built to keep sealed.
     */
    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
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
     * Shops this user owns.
     */
    public function ownedShops(): HasMany
    {
        return $this->hasMany(Shop::class, 'owner_id');
    }

    /**
     * Effective permissions per shop id, resolved once per model instance.
     *
     * @var array<int, array<int, string>>
     */
    private array $resolvedShopPermissions = [];

    /**
     * Every shop permission this user holds in their *current* shop,
     * resolved in order: the Owner role holds everything; otherwise start
     * from the role's grants in this shop, then apply this member's own
     * overrides from the shop_user pivot (true = allow, false = deny,
     * absent = inherit). Any action on a resource implies seeing it.
     *
     * Role lookups rely on Spatie's team id being the current shop (set per
     * request by SetPermissionsTeamFromCurrentShop).
     *
     * @return array<int, string>
     */
    public function shopPermissions(): array
    {
        $shopId = $this->current_shop_id;

        if (! $shopId) {
            return [];
        }

        return $this->resolvedShopPermissions[$shopId] ??= $this->resolveShopPermissions($shopId);
    }

    public function hasShopPermission(string $permission): bool
    {
        return in_array($permission, $this->shopPermissions(), true);
    }

    public function forgetResolvedShopPermissions(): void
    {
        $this->resolvedShopPermissions = [];
    }

    /**
     * @return array<int, string>
     */
    private function resolveShopPermissions(int $shopId): array
    {
        if ($this->hasRole('Owner')) {
            return ShopPermissions::all();
        }

        $granted = $this->roles->flatMap(fn ($role) => $role->permissions->pluck('name'))->all();
        $overrides = $this->shops()->whereKey($shopId)->first()?->pivot->permission_overrides ?? [];

        $effective = array_filter(
            ShopPermissions::all(),
            fn (string $name) => array_key_exists($name, $overrides) ? (bool) $overrides[$name] : in_array($name, $granted, true),
        );

        return ShopPermissions::withImpliedViews(array_values($effective));
    }
}
