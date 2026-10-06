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
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('update-quote')]
#[Description('Edit a quote that is still a DRAFT: its customer, notes, due date, and/or its line items. Send only what you want to change; if you send line_items they REPLACE all existing lines (so include the ones you want to keep). A quote that has been sent, won, lost, archived or turned into a job cannot be edited by the AI connection.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
class UpdateQuoteTool extends McpTool
{
    use DescribesQuoteLines;

    protected function permission(): string
    {
        return 'quotes.update';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The quote id (from list-quotes).')->required(),
            'customer_id' => $schema->integer()->description('Move the draft to another customer.'),
            'notes' => $schema->string()->description('Internal notes, or null to clear.'),
            'due_date' => $schema->string()->description('YYYY-MM-DD, or null to clear.'),
            ...$this->lineItemsSchema($schema),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $args = $request->all();
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'customer_id' => ['sometimes', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'line_items' => ['sometimes', 'array'],
        ]);

        $fields = ['customer_id', 'notes', 'due_date', 'line_items'];

        if (collect($fields)->every(fn (string $field) => ! array_key_exists($field, $args))) {
            throw ValidationException::withMessages(['id' => 'Send at least one of customer_id, notes, due_date or line_items to change.']);
        }

        $quote = Quote::query()->with('job')->find($data['id']);

        if (! $quote) {
            return ['updated' => false, 'reason' => 'No quote with that id in this shop.'];
        }

        if ($quote->status !== QuoteStatus::Draft || $quote->is_archived || $quote->job) {
            return ['updated' => false, 'reason' => "Only draft quotes can be edited by the AI connection. This quote is {$quote->status->value}".($quote->is_archived ? ' and archived' : '').($quote->job ? " and became job {$quote->job->job_number}" : '').'.'];
        }

        if (array_key_exists('customer_id', $data) && ! Customer::query()->whereKey($data['customer_id'])->exists()) {
            return ['updated' => false, 'reason' => 'No customer with that id in this shop.'];
        }

        $lines = array_key_exists('line_items', $data)
            ? app(QuoteLineResolver::class)->resolve($shop, $data['line_items'])
            : null;

        $before = QuoteHistory::enabled() ? QuoteHistory::snapshot($quote) : null;

        DB::transaction(function () use ($quote, $data, $lines) {
            $quote->update(collect($data)->only(['customer_id', 'notes', 'due_date'])->all());

            if ($lines !== null) {
                $quote->lineItems()->delete();
                $quote->lineItems()->createMany($lines);
            }
        });

        if ($before !== null) {
            QuoteHistory::recordSave($quote->refresh(), $before, 'Saved changes (via AI connection)');
        }

        $this->audit($quote, $agent, "Edited draft quote #{$quote->id}");

        return ['updated' => true, 'quote' => $this->presentQuote($quote->refresh(), $agent)];
    }
}
