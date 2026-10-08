<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * A record the shop's AI connection created or changed, waiting for a person
 * to look at it. ai_touched_at is never mass-assignable: only the MCP write
 * tools set it (markTouchedByAi) and only a person clears it (markReviewed),
 * so nothing a request or an AI sends can mark its own work as reviewed.
 *
 * @property Carbon|null $ai_touched_at
 */
trait NeedsAiReview
{
    public function needsAiReview(): bool
    {
        return $this->ai_touched_at !== null;
    }

    public function markTouchedByAi(): void
    {
        $this->forceFill(['ai_touched_at' => now()])->save();
    }

    public function markReviewed(): void
    {
        $this->forceFill(['ai_touched_at' => null])->save();
    }
}
