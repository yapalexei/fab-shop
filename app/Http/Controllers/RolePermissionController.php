<?php

namespace App\Http\Controllers;

use App\Enums\ShopPermission;
use App\Http\Requests\UpdateRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Which roles can do what in the current shop. Roles are per shop (Spatie
 * teams), so granting Admin a permission here only affects this shop's
 * Admins. The Owner role isn't editable - it always has everything.
 */
class RolePermissionController extends Controller
{
    public const EDITABLE_ROLES = ['Admin', 'Staff'];

    public function edit(): Response
    {
        return Inertia::render('Permissions/Edit', [
            'permissions' => self::permissionOptions(),
            'grants' => self::roleGrants(),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request): RedirectResponse
    {
        foreach (self::EDITABLE_ROLES as $roleName) {
            Role::findOrCreate($roleName)->syncPermissions($request->validated("grants.{$roleName}", []));
        }

        return back()->with('status', 'Role permissions saved.');
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function permissionOptions(): array
    {
        return array_map(fn (ShopPermission $permission) => [
            'value' => $permission->value,
            'label' => $permission->label(),
            'description' => $permission->description(),
        ], ShopPermission::cases());
    }

    /**
     * Permission names granted to each editable role in the current shop.
     *
     * @return array<string, array<int, string>>
     */
    public static function roleGrants(): array
    {
        return collect(self::EDITABLE_ROLES)
            ->mapWithKeys(fn (string $roleName) => [
                $roleName => Role::findOrCreate($roleName)->permissions->pluck('name')->values()->all(),
            ])
            ->all();
    }
}
