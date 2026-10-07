<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\SecurityLog;
use App\Support\TwoFactor;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'twoFactor' => [
                'enabled' => $request->user()->hasTwoFactorEnabled(),
                // While setup is half-done, hand the user's own secret back so
                // they can scan or type it in; never once it's confirmed.
                'setup' => $request->user()->two_factor_secret !== null && ! $request->user()->hasTwoFactorEnabled()
                    ? [
                        'secret' => $request->user()->two_factor_secret,
                        'uri' => TwoFactor::otpAuthUri($request->user(), $request->user()->two_factor_secret),
                    ]
                    : null,
                'recovery_codes_left' => count($request->user()->two_factor_recovery_codes ?? []),
                // Flashed once, right after setup / regeneration.
                'new_recovery_codes' => session('recovery_codes'),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        SecurityLog::record('account deleted', $user, ['email' => $user->email]);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
