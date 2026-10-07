<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityLog;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The second step of sign-in for users with two-factor on. The password has
 * already been checked; the session only holds the pending user id, and the
 * user is signed in here once a valid code (or recovery code) is given.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_USER = 'two_factor.user_id';

    public const SESSION_REMEMBER = 'two_factor.remember';

    public const SESSION_STARTED = 'two_factor.started_at';

    /** Minutes the user has to enter the code after the password step. */
    private const WINDOW_MINUTES = 10;

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:32'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);

        $key = 'two-factor|'.$user->getKey().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        $usedRecoveryCode = false;

        if (! empty($data['recovery_code'])) {
            $valid = TwoFactor::consumeRecoveryCode($user, $data['recovery_code']);
            $usedRecoveryCode = $valid;
        } else {
            $valid = ! empty($data['code']) && TwoFactor::verify($user, $data['code']);
        }

        if (! $valid) {
            RateLimiter::hit($key);
            SecurityLog::record('two-factor failed', $user);

            throw ValidationException::withMessages([
                empty($data['recovery_code']) ? 'code' : 'recovery_code' => 'That code is not valid.',
            ]);
        }

        RateLimiter::clear($key);

        $remember = (bool) $request->session()->pull(self::SESSION_REMEMBER, false);
        $request->session()->forget([self::SESSION_USER, self::SESSION_STARTED]);

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        if ($usedRecoveryCode) {
            SecurityLog::record('two-factor recovery code used', $user, [
                'codes_left' => count($user->two_factor_recovery_codes ?? []),
            ]);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::SESSION_USER);
        $startedAt = $request->session()->get(self::SESSION_STARTED, 0);

        if (! $id || now()->timestamp - $startedAt > self::WINDOW_MINUTES * 60) {
            $request->session()->forget([self::SESSION_USER, self::SESSION_REMEMBER, self::SESSION_STARTED]);

            return null;
        }

        $user = User::find($id);

        return $user?->hasTwoFactorEnabled() ? $user : null;
    }
}
