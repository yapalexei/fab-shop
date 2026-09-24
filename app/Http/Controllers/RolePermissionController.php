<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Support\ShopPermissions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * What each role can do in the current shop - owner only. Roles are per
 * shop (Spatie teams), so these grants only affect this shop. The Owner role
 * isn't editable: it always has everything.
 */
class RolePermissionController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Permissions/Edit', [
            'resources' => self::resourceOptions(),
            'grants' => self::roleGrants(),
            'defaults' => collect(ShopPermissions::EDITABLE_ROLES)
                ->mapWithKeys(fn (string $role) => [$role => ShopPermissions::defaultsFor($role)])
                ->all(),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request): RedirectResponse
    {
        foreach (ShopPermissions::EDITABLE_ROLES as $roleName) {
            Role::findOrCreate($roleName)->syncPermissions($request->validated("grants.{$roleName}", []));
        }

        return back()->with('status', 'Role permissions saved.');
    }

    /**
     * Resources and their actions, grouped for the permission grids.
     *
     * @return array<int, array{value: string, label: string, actions: array<int, array{action: string, permission: string, description: string}>}>
     */
    public static function resourceOptions(): array
    {
        return array_map(fn (ShopResource $resource) => [
            'value' => $resource->value,
            'label' => $resource->label(),
            'actions' => collect($resource->actions())
                ->map(fn (string $description, string $action) => [
                    'action' => $action,
                    'permission' => $resource->permission($action),
                    'description' => $description,
                ])
                ->values()
                ->all(),
        ], ShopResource::cases());
    }

    /**
     * Permission names granted to each editable role in the current shop.
     *
     * @return array<string, array<int, string>>
     */
    public static function roleGrants(): array
    {
        return collect(ShopPermissions::EDITABLE_ROLES)
            ->mapWithKeys(fn (string $roleName) => [
                $roleName => Role::findOrCreate($roleName)->permissions->pluck('name')->values()->all(),
            ])
            ->all();
    }
}
