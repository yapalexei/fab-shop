<?php

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureShopIsActive;
use App\Http\Middleware\EnsureShopIsOnboarded;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPermissionsTeamFromCurrentShop;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

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
        $middleware->web(append: [
            SetPermissionsTeamFromCurrentShop::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'feature' => EnsureFeatureEnabled::class,
            'onboarded' => EnsureShopIsOnboarded::class,
            'active' => EnsureShopIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
