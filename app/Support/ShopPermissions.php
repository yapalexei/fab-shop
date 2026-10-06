<?php

namespace App\Support;

use App\Enums\ShopResource;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The shop-level permission catalogue and each role's out-of-the-box
 * grants. The Owner role always has everything (checked directly, never
 * stored). Admin and Staff grants are stored per shop on Spatie's roles, so
 * the owner can change them on the Roles & permissions page; a member can
 * additionally carry per-member allow/deny overrides (shop_user pivot).
 */
final class ShopPermissions
{
    /** Roles whose grants are editable - Owner is always "everything". */
    public const EDITABLE_ROLES = ['Admin', 'Staff'];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return collect(ShopResource::cases())
            ->flatMap(fn (ShopResource $resource) => array_map(
                fn (string $action) => $resource->permission($action),
                array_keys($resource->actions()),
            ))
            ->values()
            ->all();
    }

    /**
     * What a role gets in a brand-new shop. Staff: day-to-day quoting and
     * moving jobs through the shop, read-only catalog, no deletes, no money.
     * Admin: runs the catalog and quotes end to end and can adjust Staff
     * access, but financials, shop settings, disconnecting QuickBooks, the
     * role defaults themselves and markup (what the shop adds on top of its
     * costs) stay with the owner until granted.
     *
     * @return array<int, string>
     */
    public static function defaultsFor(string $role): array
    {
        return match ($role) {
            'Admin' => [
                ...self::allActions(ShopResource::Customers),
                ...self::allActions(ShopResource::Quotes),
                ...self::allActions(ShopResource::Jobs),
                ...self::allActions(ShopResource::JobStages),
                ...self::allActions(ShopResource::Materials),
                ...self::allActions(ShopResource::Services),
                ...self::allActions(ShopResource::BusinessPartners),
                ...self::allActions(ShopResource::Equipment),
                ...self::allActions(ShopResource::Tooling),
                ...self::allActions(ShopResource::MaterialTypes),
                ...self::allActions(ShopResource::Procedures),
                ...self::allActions(ShopResource::Certifications),
                'quickbooks.view', 'quickbooks.update',
                ...self::allActions(ShopResource::Team),
            ],
            'Staff' => [
                'customers.view', 'customers.create', 'customers.update',
                'quotes.view', 'quotes.create', 'quotes.update',
                'jobs.view', 'jobs.create', 'jobs.update',
                'job-stages.view',
                'materials.view', 'services.view', 'business-partners.view', 'equipment.view', 'tooling.view', 'material-types.view',
                'procedures.view', 'certifications.view',
                'team.view',
            ],
            default => [],
        };
    }

    /**
     * Make sure every permission row exists (permission rows are global -
     * only their assignment to roles is per shop). One query when they
     * already do.
     */
    public static function ensurePermissionsExist(): void
    {
        $existing = DB::table('permissions')->where('guard_name', 'web')->pluck('name')->all();
        $missing = array_diff(self::all(), $existing);

        if ($missing === []) {
            return;
        }

        DB::table('permissions')->insertOrIgnore(array_map(fn (string $name) => [
            'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ], array_values($missing)));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create the shop's three roles and give Admin/Staff their defaults.
     * Only adds missing grants - never revokes one the owner already set.
     *
     * Grants are inserted directly rather than via givePermissionTo(), which
     * flushes Spatie's permission cache on every call - fine for one shop,
     * minutes of churn when a migration runs this across every shop.
     */
    public static function seedRoles(Shop $shop): void
    {
        self::ensurePermissionsExist();

        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($shop->id);

        Role::findOrCreate('Owner');

        $permissionIds = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');
        $rows = [];

        foreach (self::EDITABLE_ROLES as $roleName) {
            $roleId = Role::findOrCreate($roleName)->id;

            foreach (self::defaultsFor($roleName) as $permission) {
                $rows[] = ['permission_id' => $permissionIds[$permission], 'role_id' => $roleId];
            }
        }

        DB::table('role_has_permissions')->insertOrIgnore($rows);

        $registrar->setPermissionsTeamId($previousTeam);
        $registrar->forgetCachedPermissions();
    }

    /**
     * "Doing anything" with a resource implies being able to see it -
     * otherwise a grant like quotes.update would be useless without a way
     * to reach the quote.
     *
     * @param  array<int, string>  $granted
     * @return array<int, string>
     */
    public static function withImpliedViews(array $granted): array
    {
        foreach (ShopResource::cases() as $resource) {
            if (! array_key_exists('view', $resource->actions())) {
                continue;
            }

            $prefix = $resource->value.'.';
            $hasOther = collect($granted)->contains(fn (string $name) => str_starts_with($name, $prefix) && $name !== $prefix.'view');

            if ($hasOther) {
                $granted[] = $prefix.'view';
            }
        }

        return array_values(array_unique($granted));
    }

    /**
     * @return array<int, string>
     */
    private static function allActions(ShopResource $resource): array
    {
        return array_map(fn (string $action) => $resource->permission($action), array_keys($resource->actions()));
    }
}
