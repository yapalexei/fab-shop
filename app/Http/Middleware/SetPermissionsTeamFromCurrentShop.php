<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roles/permissions are assigned per shop (Spatie's "teams" feature, keyed by
 * shop_id). This tells that check which shop to check against: the user's
 * current, switchable shop — not every shop they belong to.
 */
class SetPermissionsTeamFromCurrentShop
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            app(PermissionRegistrar::class)->setPermissionsTeamId(Auth::user()->current_shop_id);
        }

        return $next($request);
    }
}
