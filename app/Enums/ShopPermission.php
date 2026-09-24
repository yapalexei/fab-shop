<?php

namespace App\Enums;

/**
 * Capabilities a shop owner can hand out per role (and override per team
 * member). The Owner role always has every one of these; Admin and Staff
 * start with none. Each case is also registered as a Gate ability of the
 * same name (see AppServiceProvider), e.g. Gate::allows('view-financials').
 */
enum ShopPermission: string
{
    case ViewFinancials = 'view-financials';
    case ManageShopSettings = 'manage-shop-settings';

    public function label(): string
    {
        return match ($this) {
            self::ViewFinancials => 'See financials',
            self::ManageShopSettings => 'Change shop settings',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ViewFinancials => 'Revenue, job and quote values, and win rate on the dashboard.',
            self::ManageShopSettings => 'Shop name, timezone, and dashboard thresholds (stale quotes, win rate window).',
        };
    }
}
