<?php

namespace App\Console\Commands;

use App\Actions\PurgeShop;
use App\Models\Shop;
use Illuminate\Console\Command;

class PurgeDeletedShops extends Command
{
    protected $signature = 'app:purge-deleted-shops';

    protected $description = 'Erase shops whose owner-requested deletion grace period has ended';

    public function handle(PurgeShop $purge): int
    {
        // Runs with no signed-in user: Shop is not tenant-scoped, and the
        // purge itself names the shop in every query.
        $due = Shop::query()->whereNotNull('deletion_scheduled_for')->where('deletion_scheduled_for', '<=', now())->get();

        foreach ($due as $shop) {
            try {
                $purge($shop);
                $this->info("Erased shop #{$shop->id}.");
            } catch (\Throwable $e) {
                report($e);
                $this->error("Could not erase shop #{$shop->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
