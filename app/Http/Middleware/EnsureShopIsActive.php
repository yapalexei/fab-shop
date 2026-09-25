<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a user whose current shop has been suspended by the platform admin
 * console to a notice page instead of the real app. Applied per route group
 * (not globally) alongside `onboarded` - platform admin routes deliberately
 * skip both, since a platform admin isn't acting within a shop at all.
 */
class EnsureShopIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $shop = $request->user()?->currentShop;

        if ($shop?->isSuspended()) {
            return redirect()->route('shop.suspended');
        }

        return $next($request);
    }
}
