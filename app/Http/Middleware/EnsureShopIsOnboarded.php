<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a verified user with no shop, or a shop that hasn't finished the
 * onboarding wizard, into the wizard instead of the real app. Applied per
 * route group (not globally) - the wizard's own routes, and anything a
 * signed-out or unverified visitor can reach, deliberately don't carry it.
 */
class EnsureShopIsOnboarded
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $shop = $request->user()?->currentShop;

        if (! $shop?->isOnboarded()) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
