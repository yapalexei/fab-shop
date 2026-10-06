<?php

namespace App\Mcp\Tools\Concerns;

use App\Enums\LineItemType;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The line-item shape shared by create-quote and update-quote, so the AI
 * gets one description of it, and what a quote looks like in the answer.
 */
trait DescribesQuoteLines
{
    /**
     * @return array<string, Type>
     */
    protected function lineItemsSchema(JsonSchema $schema): array
    {
        return [
            'line_items' => $schema->array()->items($schema->object([
                'type' => $schema->string()->enum(array_column(LineItemType::cases(), 'value'))->description('material, labor, equipment or tooling.')->required(),
                'description' => $schema->string()->description('What the line is, in plain words (include sizes and grades).')->required(),
                'quantity' => $schema->number()->description('How many (sheets, feet, hours, ...).')->required(),
                'material_id' => $schema->integer()->description('Catalog material id (search-catalog type=material). Fills the cost from the cheapest supplier.'),
                'service_id' => $schema->integer()->description('Catalog service id, for outsourced labor. Fills the cost from the cheapest supplier.'),
                'business_partner_id' => $schema->integer()->description('Pick a specific supplier for a material or service.'),
                'material_dimension' => $schema->string()->description('Pick a specific supplier size for a material.'),
                'user_id' => $schema->integer()->description('A team member\'s user id, for in-house labor at their hourly rate.'),
                'equipment_id' => $schema->integer()->description('Catalog equipment id. Fills the cost from its rate.'),
                'tool_id' => $schema->integer()->description('Catalog tool id. Fills the cost from its cost.'),
                'unit_cost' => $schema->number()->description('Override the cost per unit. Leave out to use the catalog\'s.'),
                'markup_percent' => $schema->number()->description('Override the markup. Leave out to use the shop\'s default for this line type.'),
            ]))->description('The quote\'s lines. Costs come from the catalog when you link a catalog item; a line with no link and no unit_cost starts at $0 for the owner to price.')->min(1)->max(50),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentQuote(Quote $quote, User $agent): array
    {
        $quote->loadMissing('customer:id,name', 'lineItems');

        return [
            'id' => $quote->id,
            'customer_id' => $quote->customer_id,
            'customer' => $quote->customer->name,
            'status' => $quote->status->value,
            'due_date' => $quote->due_date?->toDateString(),
            'notes' => $quote->notes,
            'line_count' => $quote->lineItems->count(),
            'lines' => $quote->lineItems->map(fn ($line) => [
                'type' => $line->type->value,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'priced' => (float) $line->unit_cost > 0,
            ])->all(),
            ...($agent->hasShopPermission('financials.view') ? ['total' => round($quote->total, 2)] : []),
        ];
    }
}
