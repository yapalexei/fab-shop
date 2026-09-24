<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeamMemberAccessRequest;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * One team member's role and per-member permission overrides in the
 * current shop. Overrides live on that member's shop_user row, so the same
 * person can be trusted with financials in one shop and not another.
 */
class TeamMemberAccessController extends Controller
{
    public function edit(User $member): Response
    {
        $shop = Auth::user()->currentShop;
        $membership = $this->editableMembership($shop, $member);

        return Inertia::render('Team/Access', [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->getRoleNames()->first(),
            ],
            'roles' => RolePermissionController::EDITABLE_ROLES,
            'permissions' => RolePermissionController::permissionOptions(),
            'roleGrants' => RolePermissionController::roleGrants(),
            'overrides' => (object) ($membership->pivot->permission_overrides ?? []),
        ]);
    }

    public function update(UpdateTeamMemberAccessRequest $request, User $member): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        $this->editableMembership($shop, $member);

        $member->syncRoles([Role::findOrCreate($request->validated('role'))]);

        // Only explicit allow/deny is stored; "inherit" (null) is just the
        // absence of a key, so a later change to the role applies again.
        $overrides = collect($request->validated('overrides', []))
            ->reject(fn ($value) => $value === null)
            ->map(fn ($value) => (bool) $value)
            ->all();

        $shop->members()->updateExistingPivot($member->id, [
            'permission_overrides' => $overrides === [] ? null : $overrides,
        ]);

        return redirect()->route('team.access.edit', $member)->with('status', "Access for {$member->name} saved.");
    }

    /**
     * The member's row in this shop - 404 for someone outside the shop, 403
     * for the owner, whose access can't be narrowed.
     */
    private function editableMembership(Shop $shop, User $member): User
    {
        $membership = $shop->members()->whereKey($member->id)->first();

        abort_unless($membership, 404);
        abort_if($member->id === $shop->owner_id || $member->hasRole('Owner'), 403, "The shop owner's access can't be changed.");

        return $membership;
    }
}
