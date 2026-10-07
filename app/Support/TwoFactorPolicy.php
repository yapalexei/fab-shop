<?php

namespace App\Support;

use App\Models\PlatformSetting;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Who must have two-factor sign-in on, and by when.
 *
 * The platform admin sets a default (roles + grace days). A shop's owner can
 * follow it or override it with their own - including "nobody", which turns
 * the requirement off for that shop. Each shop member's role in that shop
 * decides whether the requirement applies to them; the deadline is the grace
 * period counted from when the policy last changed, or from when they joined
 * the shop if that was later, so a new teammate always gets the full period.
 *
 * Two-factor itself stays optional for anyone the policy doesn't cover.
 */
class TwoFactorPolicy
{
    public const ROLES = ['Owner', 'Admin', 'Staff'];

    public const DEFAULT_GRACE_DAYS = 14;

    public const MAX_GRACE_DAYS = 90;

    /**
     * The platform-wide default.
     *
     * @return array{roles: list<string>, grace_days: int, set_at: ?CarbonImmutable, source: string}
     */
    public static function platform(): array
    {
        $setting = PlatformSetting::current();

        return [
            'roles' => self::cleanRoles($setting->two_factor_required_roles ?? []),
            'grace_days' => $setting->two_factor_grace_days ?? self::DEFAULT_GRACE_DAYS,
            'set_at' => $setting->two_factor_policy_set_at?->toImmutable(),
            'source' => 'platform',
        ];
    }

    /**
     * What applies to this shop: its own override if it has one, otherwise
     * the platform default.
     *
     * @return array{roles: list<string>, grace_days: int, set_at: ?CarbonImmutable, source: string}
     */
    public static function forShop(Shop $shop): array
    {
        if ($shop->two_factor_required_roles === null) {
            return self::platform();
        }

        return [
            'roles' => self::cleanRoles($shop->two_factor_required_roles),
            'grace_days' => $shop->two_factor_grace_days ?? self::platform()['grace_days'],
            'set_at' => $shop->two_factor_policy_set_at?->toImmutable(),
            'source' => 'shop',
        ];
    }

    /**
     * Whether $user must set up two-factor in $shop, and by when. Null when
     * nothing is required of them (already on, not a covered role, an AI
     * agent, or the policy is off).
     *
     * @return array{deadline: CarbonImmutable, overdue: bool, days_left: int}|null
     */
    public static function requirementFor(User $user, Shop $shop): ?array
    {
        if ($user->is_agent || $user->hasTwoFactorEnabled()) {
            return null;
        }

        $policy = self::forShop($shop);

        if ($policy['roles'] === []) {
            return null;
        }

        $role = self::rolesInShop($shop)[$user->id] ?? null;

        if (! $role || ! in_array($role, $policy['roles'], true)) {
            return null;
        }

        $joined = DB::table('shop_user')->where('shop_id', $shop->id)->where('user_id', $user->id)->value('created_at');

        return self::deadline($policy, $joined ? CarbonImmutable::parse($joined) : null);
    }

    /**
     * Everyone in $shop the policy currently covers who hasn't set up
     * two-factor yet, with their deadline.
     *
     * @return list<array{user: User, deadline: CarbonImmutable}>
     */
    public static function affectedMembers(Shop $shop): array
    {
        $policy = self::forShop($shop);

        if ($policy['roles'] === []) {
            return [];
        }

        $roles = self::rolesInShop($shop);
        $joined = DB::table('shop_user')->where('shop_id', $shop->id)->pluck('created_at', 'user_id');

        return $shop->members()->get()
            ->filter(fn (User $user) => ! $user->hasTwoFactorEnabled() && in_array($roles[$user->id] ?? null, $policy['roles'], true))
            ->map(fn (User $user) => [
                'user' => $user,
                'deadline' => self::deadline($policy, isset($joined[$user->id]) ? CarbonImmutable::parse($joined[$user->id]) : null)['deadline'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{roles: list<string>, grace_days: int, set_at: ?CarbonImmutable, source: string}  $policy
     * @return array{deadline: CarbonImmutable, overdue: bool, days_left: int}
     */
    private static function deadline(array $policy, ?CarbonImmutable $joinedAt): array
    {
        $start = collect([$policy['set_at'], $joinedAt])->filter()->max() ?? CarbonImmutable::now();
        $deadline = $start->addDays($policy['grace_days']);
        $now = CarbonImmutable::now();

        return [
            'deadline' => $deadline,
            'overdue' => $deadline->lte($now),
            'days_left' => max(0, (int) ceil($now->diffInHours($deadline, false) / 24)),
        ];
    }

    /**
     * Each member's role name in the shop, read straight from Spatie's pivot
     * (the team context may point at a different shop, e.g. in a queued job).
     *
     * @return array<int, string>
     */
    public static function rolesInShop(Shop $shop): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.shop_id', $shop->id)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->pluck('roles.name', 'model_has_roles.model_id')
            ->all();
    }

    /**
     * @param  array<int, mixed>  $roles
     * @return list<string>
     */
    public static function cleanRoles(array $roles): array
    {
        return array_values(array_intersect(self::ROLES, $roles));
    }
}
