<?php

namespace App\Mcp\Tools\Catalog;

use App\Enums\BillingMethod;
use App\Models\Equipment;
use App\Models\Shop;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('update-equipment')]
#[Description('Change a catalog machine or its rate. Send only the fields to change.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class UpdateEquipmentTool extends CatalogUpdateTool
{
    protected function resource(): string
    {
        return 'equipment';
    }

    protected function model(): string
    {
        return Equipment::class;
    }

    protected function noun(): string
    {
        return 'equipment';
    }

    protected function attributes(): array
    {
        return ['name', 'billing_method', 'rate', 'notes'];
    }

    protected function fields(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The machine\'s name, e.g. "CNC plasma table".')->required(),
            'billing_method' => $schema->string()->enum(['hourly', 'flat'])->description('How it is charged: per hour, or a flat amount per use.')->required(),
            'rate' => $schema->number()->description('The rate in dollars (per hour, or flat).')->required(),
            'notes' => $schema->string(),
        ];
    }

    protected function rules(Shop $shop): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'billing_method' => ['required', Rule::enum(BillingMethod::class)],
            'rate' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function present(Model $record): array
    {
        return $record->only(['id', 'name', 'billing_method', 'rate', 'notes']);
    }
}
