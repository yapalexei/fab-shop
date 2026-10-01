<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of a quote's change log. `changes` is a list of
 * {type: changed|added|removed, subject, from, to} for a save; workflow
 * events (sent, won, ...) carry just a summary.
 */
class QuoteHistoryEntry extends Model
{
    use BelongsToShop;

    protected $fillable = ['quote_id', 'user_id', 'event', 'summary', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
