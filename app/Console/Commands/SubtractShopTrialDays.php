<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shop:subtract-trial-days {shop_id} {days}')]
#[Description('Testing tool: shortens a shop\'s trial by N days, e.g. to see what a near-expired or already-expired trial looks like. There\'s no real production reason to shorten a trial - extending one for real goes through the platform admin console instead.')]
class SubtractShopTrialDays extends Command
{
    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This is a testing tool - refusing to run in production.');

            return self::FAILURE;
        }

        $shopId = (int) $this->argument('shop_id');
        $shop = Shop::find($shopId);

        if (! $shop) {
            $this->error("No shop found with id {$shopId}.");

            return self::FAILURE;
        }

        $days = (int) $this->argument('days');

        if ($days < 1) {
            $this->error('days must be a positive integer.');

            return self::FAILURE;
        }

        if ($shop->trial_ends_at === null) {
            $this->error("{$shop->name} has no trial_ends_at to subtract from.");

            return self::FAILURE;
        }

        $shop->update(['trial_ends_at' => $shop->trial_ends_at->copy()->subDays($days)]);

        $this->info("Subtracted {$days} day(s) from {$shop->name}'s trial - now ends {$shop->trial_ends_at->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
