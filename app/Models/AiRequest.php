<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\AiRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A log row for one AI call - usage and cost, never the prompt or answer
 * (those contain customer data). Written by the service that made the call,
 * with shop_id passed explicitly since that service may run in a queue.
 */
class AiRequest extends Model
{
    /** @use HasFactory<AiRequestFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'shop_id', 'user_id', 'feature', 'provider', 'model',
        'input_tokens', 'output_tokens', 'latency_ms', 'status',
    ];
}
