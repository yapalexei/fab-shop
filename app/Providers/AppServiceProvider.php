<?php

namespace App\Providers;

use App\Models\User;
use App\Support\ShopPermissions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
