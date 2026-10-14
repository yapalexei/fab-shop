<?php

namespace App\Support;

use App\Models\User;

/**
 * Which edition of the product this install is: the multi-tenant cloud
 * service (default), or a single shop's own copy (APP_MODE=selfhosted).
 *
 * Same code, same schema - a self-hosted install is simply one tenant. What
 * changes is everything that only makes sense when *we* run the service for
 * many shops: plans, trials, seat limits, add-ons, the platform admin
 * console, public sign-up, the marketing page and our legal pages.
 * See docs/context/self-hosted.md.
 */
class Edition
{
    public const SELF_HOSTED = 'selfhosted';

    public static function selfHosted(): bool
    {
        return config('app.mode') === self::SELF_HOSTED;
    }

    public static function cloud(): bool
    {
        return ! self::selfHosted();
    }

    /**
     * Whether the one-time setup has been done. The first account is the shop
     * owner; once any account exists the setup page is closed for good, so a
     * stranger can't claim an install that is already in use.
     */
    public static function installed(): bool
    {
        return User::query()->exists();
    }
}
