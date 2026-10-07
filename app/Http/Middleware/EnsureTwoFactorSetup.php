<?php

namespace App\Http\Middleware;

use App\Support\TwoFactorPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the two-factor requirement (TwoFactorPolicy) once its grace period
 * has run out: the person can still reach their profile (where setup lives),
 * switch shops, sign out and read the legal pages, and is sent to the profile
 * from everywhere else until they finish setup. Before the deadline nothing
 * is blocked - the layout just shows a dismissible reminder, fed by the
 * `two_factor_requirement` request attribute set here.
 */
class EnsureTwoFactorSetup
{
    private const ALLOWED = [
        'profile.edit', 'two-factor.*', 'logout', 'current-shop.update',
        'verification.*', 'legal.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $shop = $user && ! $user->is_agent ? $user->currentShop : null;

        if (! $shop) {
            return $next($request);
        }

        $requirement = TwoFactorPolicy::requirementFor($user, $shop);
        $request->attributes->set('two_factor_requirement', $requirement);

        if ($requirement && $requirement['overdue'] && ! $request->routeIs(...self::ALLOWED)) {
            return redirect()->route('profile.edit')
                ->with('error', 'Your shop requires two-factor sign-in. Please finish setting it up below to continue.');
        }

        return $next($request);
    }
}
