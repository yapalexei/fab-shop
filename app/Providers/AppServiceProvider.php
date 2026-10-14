<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationEvents;
use App\Models\User;
use App\Support\ShopPermissions;
use App\Support\Ssr\PublicPagesGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Ssr\Gateway;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Server-render only the public pages, with a short timeout - see the class.
        $this->app->bind(Gateway::class, PublicPagesGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Per agent, not per IP: one shop's runaway AI client shouldn't be
        // able to starve another's, and clients share IPs behind proxies.
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute((int) config('ai.mcp_requests_per_minute'))
            ->by($request->user()?->id ?: $request->ip()));

        // Route markers read by App\Support\Seo (sitemap.xml / robots.txt).
        // `lastmod` is a config key holding a date, e.g. 'legal.versions.terms'.
        Route::macro('sitemap', function (?string $lastmod = null) {
            $this->action['sitemap'] = ['lastmod' => $lastmod];

            return $this;
        });
        Route::macro('noindex', function () {
            $this->action['noindex'] = true;

            return $this;
        });

        Event::subscribe(LogAuthenticationEvents::class);

        Vite::prefetch(concurrency: 3);

        // Owner-only: editing what each *role* can do. Never delegable, or
        // an Admin could grant their own role anything.
        Gate::define('manage-shop', fn (User $user): bool => $user->hasRole('Owner'));

        // Platform-level staff, entirely outside the per-shop role system -
        // see User::isPlatformAdmin().
        Gate::define('platform-admin', fn (User $user): bool => $user->isPlatformAdmin());

        // One ability per shop permission, e.g. Gate::allows('quotes.delete').
        foreach (ShopPermissions::all() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasShopPermission($permission));
        }
    }
}
