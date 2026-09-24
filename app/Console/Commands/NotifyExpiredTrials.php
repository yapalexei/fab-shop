<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Notifications\TrialEnded;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:notify-expired-trials')]
#[Description('Notify shop owners whose trial has ended and who have not upgraded')]
class NotifyExpiredTrials extends Command
{
    /**
     * Execute the console command.
     *
     * Gating is computed from trial_ends_at/plan_id at read time, so this
     * command changes no access-control data - it only sends the one-time
     * "your trial ended" email.
     */
    public function handle(): void
    {
        Shop::query()
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->whereNull('trial_ended_notified_at')
            ->whereHas('plan', fn ($query) => $query->where('key', 'free'))
            ->each(function (Shop $shop): void {
                $shop->owner->notify(new TrialEnded($shop));
                $shop->update(['trial_ended_notified_at' => now()]);
            });
    }
}
