<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeamMemberRateRequest;
use App\Models\User;
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

        return Inertia::render('Team/Index', [
            'members' => $shop->members()->get()->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->getRoleNames()->first(),
                'hourly_rate' => $member->pivot->hourly_rate !== null
                    ? (float) $member->pivot->hourly_rate
                    : null,
            ]),
            'canManageTeam' => Gate::allows('manage-shop'),
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
}
