<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserAvatarController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['avatar' => ['required', ...Images::rules()]]);

        $request->user()->addMediaFromRequest('avatar')->toMediaCollection('avatar');

        return back()->with('status', 'Profile picture updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->clearMediaCollection('avatar');

        return back()->with('status', 'Profile picture removed.');
    }

    /**
     * Anyone can see their own picture; otherwise only members of the
     * viewer's current shop can see it - never another shop's people.
     */
    public function show(Request $request, User $user): StreamedResponse
    {
        $viewer = $request->user();

        abort_unless(
            $viewer->is($user) || $viewer->currentShop?->members()->where('users.id', $user->id)->exists(),
            404,
        );

        return Images::respond($request, $user, 'avatar');
    }
}
