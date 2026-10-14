<?php

namespace App\Console\Commands;

use App\Actions\PropagateTierChange;
use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:apply-scheduled-plan-changes')]
#[Description('Apply tier price changes that are due, and catch up any shop still on an older version')]
class ApplyScheduledPlanChanges extends Command
{
    /**
     * Two jobs, both safe to repeat. First, shops whose announced price
     * increase has reached its date are moved to it. Second, every tier's
     * current version is run against the shops still on an older one
     * (PropagateTierChange is idempotent), so a lower price reaches a shop
     * that was already on an old version when the rule was introduced, or that
     * was put there by hand since. Hourly, so a date isn't up to a day late.
     */
    public function handle(PropagateTierChange $propagate): void
    {
        Shop::query()
            ->whereNotNull('pending_plan_id')
            ->where('pending_plan_effective_at', '<=', now())
            ->with('pendingPlan')
            ->each(function (Shop $shop): void {
                if ($shop->pendingPlan !== null) {
                    $shop->moveToPlan($shop->pendingPlan);
                }
            });

        Plan::query()->current()->where('version', '>', 1)->each(fn (Plan $plan) => $propagate($plan));
    }
}
