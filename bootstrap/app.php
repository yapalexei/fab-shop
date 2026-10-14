<?php

use App\Http\Middleware\AuthenticateMcpAgent;
use App\Http\Middleware\CloudOnly;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureShopIsActive;
use App\Http\Middleware\EnsureShopIsOnboarded;
use App\Http\Middleware\EnsureTwoFactorSetup;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SelfHostedOnly;
use App\Http\Middleware\SetPermissionsTeamFromCurrentShop;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SetPermissionsTeamFromCurrentShop must run before Inertia shares
        // props - the shared `auth.can` flags check roles, which need
        // Spatie's team id already pointed at the current shop.
        // Railway (like most PaaS hosts) terminates TLS at its edge proxy,
        // so without this Laravel sees plain http and generates http:// URLs.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            SecurityHeaders::class,
            // Ends a browser's session once the password has changed elsewhere.
            AuthenticateSession::class,
            SetPermissionsTeamFromCurrentShop::class,
            EnsureTwoFactorSetup::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'feature' => EnsureFeatureEnabled::class,
            'mcp.agent' => AuthenticateMcpAgent::class,
            'onboarded' => EnsureShopIsOnboarded::class,
            'active' => EnsureShopIsActive::class,
            'cloud' => CloudOnly::class,
            'selfhosted' => SelfHostedOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A shop's AI provider key must never be flashed back into the
        // session as "old input" when its form fails validation.
        $exceptions->dontFlash(['api_key']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'mcp*') || $request->expectsJson(),
        );
    })->create();
