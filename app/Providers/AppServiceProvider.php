<?php

namespace App\Providers;

use App\Models\User;
use App\Support\ShopPermissions;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
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

        // Not registered by default outside Fortify/Jetstream - without this,
        // Registered fires (it already does, in RegisteredUserController) but
        // nothing actually emails the verification link.
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Owner-only: editing what each *role* can do. Never delegable, or
        // an Admin could grant their own role anything.
        Gate::define('manage-shop', fn (User $user): bool => $user->hasRole('Owner'));

        // One ability per shop permission, e.g. Gate::allows('quotes.delete').
        foreach (ShopPermissions::all() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasShopPermission($permission));
        }
    }
}
