<?php

namespace App\Http\Middleware;

use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides a route in a self-hosted install (404, as if it didn't exist).
 * The routes stay registered either way, so the route table the frontend
 * builds links from is the same in both editions - only the page is gone.
 */
class CloudOnly
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Edition::selfHosted(), 404);

        return $next($request);
    }
}
