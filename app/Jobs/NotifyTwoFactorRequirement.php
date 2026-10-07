<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Notifications\TwoFactorRequired;
use App\Support\TwoFactorPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Emails everyone a two-factor policy newly covers. With a shop id, that
 * shop's own change; with null, a platform-default change - which reaches
 * every shop still following the default. Someone in several shops gets one
 * email, with their earliest deadline.
 *
 * Runs without an authenticated user, so it queries shops explicitly rather
 * than relying on the tenant scope.
 */
class NotifyTwoFactorRequirement implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $shopId = null) {}

    public function handle(): void
    {
        $shops = Shop::query()
            ->whereNull('suspended_at')
            ->when($this->shopId, fn ($q) => $q->whereKey($this->shopId), fn ($q) => $q->whereNull('two_factor_required_roles'))
            ->get();

        $earliest = [];
        $users = [];

        foreach ($shops as $shop) {
            foreach (TwoFactorPolicy::affectedMembers($shop) as $row) {
                $id = $row['user']->id;
                $users[$id] = $row['user'];
                $earliest[$id] = isset($earliest[$id]) ? min($earliest[$id], $row['deadline']) : $row['deadline'];
            }
        }

        foreach ($users as $id => $user) {
            $user->notify(new TwoFactorRequired($earliest[$id]));
        }
    }
}
