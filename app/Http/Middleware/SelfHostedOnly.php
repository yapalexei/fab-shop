<?php

namespace App\Http\Middleware;

use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes that exist only in a self-hosted install (the first-run setup page).
 */
class SelfHostedOnly
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Edition::selfHosted(), 404);

        return $next($request);
    }
}
