<?php

namespace App\Http\Controllers;

use App\Support\SecurityLog;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Turning two-factor sign-in on and off from the profile page. Setup is two
 * steps (get a secret, then prove a code from the app works) so a typo can
 * never lock someone out; turning it off or minting new recovery codes asks
 * for the password again.
 */
class TwoFactorSettingsController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back();
        }

        $user->forceFill([
            'two_factor_secret' => TwoFactor::newSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return back();
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);

        if ($user->two_factor_secret === null || $user->hasTwoFactorEnabled()) {
            return back();
        }

        if (! TwoFactor::verify($user, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is not valid. Check your authenticator app and try again.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        SecurityLog::record('two-factor enabled', $user);

        return back()->with('recovery_codes', TwoFactor::regenerateRecoveryCodes($user));
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return back();
        }

        SecurityLog::record('two-factor recovery codes regenerated', $user);

        return back()->with('recovery_codes', TwoFactor::regenerateRecoveryCodes($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        $wasEnabled = $user->hasTwoFactorEnabled();

        TwoFactor::disable($user);

        if ($wasEnabled) {
            SecurityLog::record('two-factor disabled', $user);
        }

        return back();
    }
}
