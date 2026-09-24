<?php

namespace App\Console\Commands\Demo;

use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Job;
use App\Models\MarkupDefault;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

/**
 * Shared guard + teardown for the demo:* commands. These build realistic,
 * interconnected data (shops, teams, catalogs, quotes, jobs) purely for
 * manually clicking around in dev - never meant to run anywhere real data
 * lives, hence the double gate in guardDemoMode().
 */
abstract class DemoSeedCommand extends Command
{
    /**
     * @return bool false if the command should abort
     */
    protected function guardDemoMode(): bool
    {
        if (app()->isProduction()) {
            $this->components->error('Refusing to run: APP_ENV is production.');

            return false;
        }

        if (config('app.mode') !== 'demo') {
            $this->components->error('Refusing to run: set APP_MODE=demo in your .env first (see CLAUDE.md).');

            return false;
        }

        return true;
    }

    /**
     * Deletes a shop and everything under it, in dependency order (jobs
     * before the quotes/customers they restrict-reference, quotes before
     * the customers they restrict-reference), then removes any member who
     * isn't tied to some other shop - so resetting one demo shop doesn't
     * delete a user who's deliberately shared across two of them.
     *
     * Console commands run with no authenticated user, so BelongsToShop's
     * global scope never applies here (see ShopScope) - every query below
     * has to filter by shop_id explicitly, unlike request-scoped app code.
     */
    protected function deleteShopAndMembers(Shop $shop): void
    {
        Job::where('shop_id', $shop->id)->get()->each(function (Job $job) {
            Activity::where('subject_type', Job::class)->where('subject_id', $job->id)->delete();
            $job->delete();
        });

        Quote::where('shop_id', $shop->id)->get()->each(fn (Quote $quote) => $quote->delete());

        Customer::where('shop_id', $shop->id)->delete();
        Material::where('shop_id', $shop->id)->delete();
        Service::where('shop_id', $shop->id)->delete();
        BusinessPartner::where('shop_id', $shop->id)->delete();
        Equipment::where('shop_id', $shop->id)->delete();
        Tool::where('shop_id', $shop->id)->delete();
        MaterialType::where('shop_id', $shop->id)->delete();
        MarkupDefault::where('shop_id', $shop->id)->delete();

        $memberIds = $shop->members()->pluck('users.id');

        $shop->delete();

        User::whereIn('id', $memberIds)
            ->whereDoesntHave('ownedShops')
            ->whereDoesntHave('shops')
            ->delete();
    }

    /**
     * @param  array<int, array{name: string, email: string, role: string}>  $rows
     */
    protected function printCredentials(array $rows): void
    {
        $this->newLine();
        $this->components->info('Login credentials (password for all: "password")');
        $this->table(['Name', 'Email', 'Role'], $rows);
    }
}
