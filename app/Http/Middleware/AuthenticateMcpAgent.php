<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after Sanctum on the MCP endpoint and decides who is really allowed
 * through: only a shop's AI agent holding a token minted for MCP, in a shop
 * that is entitled to AI right now. A person's token (or session) is
 * refused, so MCP can never act as a human - and because an agent's shop
 * never changes, nothing it does can land in another tenant.
 *
 * Strict hasFeature(), not the read-only canViewFeature() the feature
 * middleware allows on safe requests: when AI access lapses, the endpoint
 * stops outright (the tokens are kept, and work again on resubscribing).
 */
class AuthenticateMcpAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $agent = $request->user();

        abort_unless($agent?->is_agent && $agent->tokenCan('mcp'), 403, 'This token cannot be used for MCP.');

        $shop = $agent->currentShop;

        abort_unless($shop !== null && $shop->suspended_at === null, 403, 'This shop is not active.');
        abort_unless($shop->hasFeature(Feature::AiAgent), 403, 'AI agent access is not part of this shop\'s plan.');

        // Roles/permissions are per shop; the web stack sets this from the
        // logged-in user, but this route doesn't run that middleware.
        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);

        return $next($request);
    }
}
