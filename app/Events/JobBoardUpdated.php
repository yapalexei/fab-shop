<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A "something changed, refetch" ping for the job board - no payload
 * beyond the shop, since the board just calls back to Inertia for fresh
 * data rather than trying to keep a client-side copy of every job in
 * sync from broadcast payloads alone.
 *
 * ShouldBroadcastNow, not ShouldBroadcast - a plain ShouldBroadcast event
 * goes out via a queued job, which would leave the board silently stale
 * whenever a queue worker isn't running. That trade-off is fine for the
 * quote PDF/email (arriving a little late doesn't matter); it defeats the
 * purpose of a "live" board.
 */
class JobBoardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $shopId) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("shop.{$this->shopId}.jobs"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'JobBoardUpdated';
    }
}
