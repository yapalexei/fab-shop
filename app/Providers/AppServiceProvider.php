<?php

namespace App\Providers;

use App\Enums\ShopPermission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        Vite::prefetch(concurrency: 3);

        // Owner-only: team rates, markup defaults, and who can do what -
        // never delegable, or an Admin could grant themselves anything.
        Gate::define('manage-shop', fn (User $user): bool => $user->hasRole('Owner'));

        foreach (ShopPermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasShopPermission($permission));
        }
    }
}
