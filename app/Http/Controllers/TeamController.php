<?php

namespace App\Http\Controllers;

use App\Actions\SendShopInvitation;
use App\Http\Requests\SendTeamInvitationRequest;
use App\Http\Requests\UpdateTeamMemberRateRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Support\ShopPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        $shop = Auth::user()->currentShop;
        $canManageAccess = Gate::allows('team.manage-access');
        $isOwner = Gate::allows('manage-shop');

        return Inertia::render('Team/Index', [
            'members' => $shop->members()->get()->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->getRoleNames()->first(),
                'is_owner' => $member->id === $shop->owner_id,
                // Same rules TeamMemberAccessController enforces: never the
                // owner or yourself, and only Staff unless you're the owner.
                'can_edit_access' => $canManageAccess
                    && $member->id !== $shop->owner_id
                    && ! $member->is(Auth::user())
                    && ($isOwner || $member->hasRole('Staff')),
                'hourly_rate' => $member->pivot->hourly_rate !== null
                    ? (float) $member->pivot->hourly_rate
                    : null,
            ]),
            'canManageTeam' => Gate::allows('team.update'),
            'canManageAccess' => $canManageAccess,
            // Inviting is owner-only for now - it grants shop access, not
            // just an editable setting, so it stays off the delegable
            // team.manage-access permission on purpose.
            'canInvite' => $isOwner,
            'invitableRoles' => ShopPermissions::EDITABLE_ROLES,
            'pendingInvitations' => $isOwner
                ? Invitation::where('shop_id', $shop->id)->whereNull('accepted_at')->latest()->get(['email', 'role', 'expires_at'])
                : [],
        ]);
    }

    public function update(UpdateTeamMemberRateRequest $request, User $member): RedirectResponse
    {
        $shop = Auth::user()->currentShop;

        abort_unless($shop->members()->where('users.id', $member->id)->exists(), 404);

        $shop->members()->updateExistingPivot($member->id, [
            'hourly_rate' => $request->validated('hourly_rate'),
        ]);

        return redirect()->route('team.index');
    }

    /**
     * Invite someone by email - the same action onboarding's team step
     * uses, reachable any time afterwards too (people join a shop well
     * after it's set up, not just during onboarding).
     */
    public function invite(SendTeamInvitationRequest $request, SendShopInvitation $sendInvitation): RedirectResponse
    {
        $shop = Auth::user()->currentShop;

        $invitation = $sendInvitation($shop, $request->validated('email'), $request->validated('role'), Auth::user());

        return redirect()->route('team.index')->with('status', "Invited {$invitation->email}.");
    }
}
