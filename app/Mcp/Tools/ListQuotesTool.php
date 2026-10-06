<?php

namespace App\Mcp\Tools;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the shop\'s quotes, newest first. Filter by status, by customer, or to only quotes that have gone stale (sent with no reply past the shop\'s follow-up window). Amounts are included only if this connection may see financials.')]
#[Name('list-quotes')]
#[IsReadOnly]
#[IsIdempotent]
class ListQuotesTool extends McpTool
{
    protected function permission(): string
    {
        return 'quotes.view';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(array_column(QuoteStatus::cases(), 'value'))->description('Only quotes in this status.'),
            'stale_only' => $schema->boolean()->description('Only sent quotes with no reply for longer than the shop\'s stale-quote window.')->default(false),
            'customer_id' => $schema->integer()->description('Only this customer\'s quotes (an id from search-customers).'),
            'include_archived' => $schema->boolean()->description('Include archived quotes (default false).')->default(false),
            'limit' => $schema->integer()->description('How many to return, 1-50 (default 20).')->default(20),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(QuoteStatus::class)],
            'stale_only' => ['nullable', 'boolean'],
            'customer_id' => ['nullable', 'integer'],
            'include_archived' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $quotes = Quote::query()
            ->with(['customer:id,name', 'lineItems'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['stale_only'] ?? false, fn ($query) => $query->stale($shop))
            ->when($data['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->unless($data['include_archived'] ?? false, fn ($query) => $query->whereNull('archived_at'))
            ->latest()
            ->limit($data['limit'] ?? 20)
            ->get();

        $money = $agent->hasShopPermission('financials.view');

        return [
            'stale_after_days' => $shop->staleQuoteDays(),
            'quotes' => $quotes->map(fn (Quote $quote) => [
                'id' => $quote->id,
                'customer_id' => $quote->customer_id,
                'customer' => $quote->customer->name,
                'status' => $quote->status->value,
                'archived' => $quote->is_archived,
                'sent_at' => $quote->sent_at?->toIso8601String(),
                'days_since_sent' => $quote->sent_at ? (int) $quote->sent_at->diffInDays(now()) : null,
                'due_date' => $quote->due_date?->toDateString(),
                'line_count' => $quote->lineItems->count(),
                ...($money ? ['total' => round($quote->total, 2)] : []),
            ])->all(),
        ];
    }
}
