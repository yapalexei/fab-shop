<?php

namespace App\Mcp\Tools;

use App\Enums\QuoteStatus;
use App\Mcp\Support\QuoteLineResolver;
use App\Mcp\Tools\Concerns\DescribesQuoteLines;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Shop;
use App\Models\User;
use App\Support\QuoteHistory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create-quote')]
#[Description('Create a DRAFT quote for a customer with line items. It is only ever a draft: it is not sent to the customer, and a person reviews and sends it from the app. Look up the customer (search-customers) and catalog items (search-catalog) first so lines link to real items and get real costs.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class CreateQuoteTool extends McpTool
{
    use DescribesQuoteLines;

    protected function permission(): string
    {
        return 'quotes.create';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->description('The customer id (from search-customers).')->required(),
            'notes' => $schema->string()->description('Internal notes on the quote.'),
            'due_date' => $schema->string()->description('When the customer needs it by, YYYY-MM-DD.'),
            ...$this->lineItemsSchema($schema),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'line_items' => ['required', 'array'],
        ]);

        $customer = Customer::query()->find($data['customer_id']);

        if (! $customer) {
            return ['created' => false, 'reason' => 'No customer with that id in this shop. Use search-customers (or create-customer first).'];
        }

        $lines = app(QuoteLineResolver::class)->resolve($shop, $data['line_items']);

        $quote = DB::transaction(function () use ($data, $customer, $lines) {
            $quote = Quote::create([
                'customer_id' => $customer->id,
                'notes' => $data['notes'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => QuoteStatus::Draft,
            ]);

            $quote->lineItems()->createMany($lines);

            return $quote;
        });

        QuoteHistory::log($quote, 'created', 'Quote created (via AI connection)');
        $this->audit($quote, $agent, "Created draft quote #{$quote->id} for {$customer->name}");

        return ['created' => true, 'quote' => $this->presentQuote($quote->refresh(), $agent)];
    }
}
