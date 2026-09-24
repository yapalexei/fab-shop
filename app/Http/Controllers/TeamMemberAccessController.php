<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeamMemberAccessRequest;
use App\Models\Shop;
use App\Models\User;
use App\Support\ShopPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * One team member's role and per-member permission overrides in the
 * current shop. Overrides live on that member's shop_user row, so the same
 * person can be trusted with more in one shop than another.
 *
 * Open to anyone with team.manage-access (the owner, and Admins by
 * default), with guard rails so it can't be used to escalate: nobody edits
 * their own access or the owner's; only the owner can change roles or touch
 * an Admin's access; and a non-owner can only *allow* things they're
 * allowed themselves.
 */
class TeamMemberAccessController extends Controller
{
    public function edit(User $member): Response
    {
        $shop = Auth::user()->currentShop;
        $membership = $this->editableMembership($shop, $member);
        $isOwner = Gate::allows('manage-shop');

        return Inertia::render('Team/Access', [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->getRoleNames()->first(),
            ],
            'roles' => ShopPermissions::EDITABLE_ROLES,
            'canChangeRole' => $isOwner,
            'resources' => RolePermissionController::resourceOptions(),
            'roleGrants' => RolePermissionController::roleGrants(),
            'overrides' => (object) ($membership->pivot->permission_overrides ?? []),
            'grantable' => $isOwner ? ShopPermissions::all() : Auth::user()->shopPermissions(),
        ]);
    }

    public function update(UpdateTeamMemberAccessRequest $request, User $member): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        $this->editableMembership($shop, $member);

        if (Gate::allows('manage-shop')) {
            $member->syncRoles([Role::findOrCreate($request->validated('role'))]);
        }

        // Only explicit allow/deny is stored; "inherit" (null) is just the
        // absence of a key, so a later change to the role applies again.
        $overrides = collect($request->validated('overrides'))
            ->reject(fn (array $row) => $row['value'] === null)
            ->mapWithKeys(fn (array $row) => [$row['permission'] => (bool) $row['value']])
            ->all();

        $shop->members()->updateExistingPivot($member->id, [
            'permission_overrides' => $overrides === [] ? null : $overrides,
        ]);

        return redirect()->route('team.access.edit', $member)->with('status', "Access for {$member->name} saved.");
    }

    /**
     * The member's row in this shop, or abort: 404 for someone outside the
     * shop, 403 for the owner, yourself, or (unless you're the owner)
     * anyone who isn't Staff.
     */
    private function editableMembership(Shop $shop, User $member): User
    {
        $membership = $shop->members()->whereKey($member->id)->first();

        abort_unless($membership, 404);
        abort_if($member->id === $shop->owner_id || $member->hasRole('Owner'), 403, "The shop owner's access can't be changed.");
        abort_if($member->is(Auth::user()), 403, "You can't change your own access.");
        abort_if(! Gate::allows('manage-shop') && ! $member->hasRole('Staff'), 403, "Only the shop owner can change an Admin's access.");

        return $membership;
    }
}
