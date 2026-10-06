<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

/**
 * A log row for one tool call made over MCP by a shop's connected AI -
 * which tool, whether it worked or was refused, how long it took. Never the
 * arguments or the result (customer data). Written by McpTool with an
 * explicit shop_id.
 */
class McpRequest extends Model
{
    use BelongsToShop;

    protected $fillable = ['shop_id', 'agent_id', 'tool', 'status', 'latency_ms'];
}
