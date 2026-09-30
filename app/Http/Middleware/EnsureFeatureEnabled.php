<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $shop = $request->user()?->currentShop;

        $feature = Feature::from($feature);

        // A shop that lost the feature (trial ended, plan change) can still
        // read what it created; anything that writes needs the feature.
        $allowed = $request->isMethodSafe()
            ? $shop?->canViewFeature($feature)
            : $shop?->hasFeature($feature);

        abort_unless($allowed, 403);

        return $next($request);
    }
}
