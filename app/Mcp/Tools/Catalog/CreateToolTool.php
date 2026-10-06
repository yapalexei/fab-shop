<?php

namespace App\Mcp\Tools\Catalog;

use App\Models\Shop;
use App\Models\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create-tool')]
#[Description('Add a tool (drill bit, saw blade, cutting wheel...) to the tooling catalog, with the material types it is rated to cut. Refuses a duplicate name unless allow_duplicate is true.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class CreateToolTool extends CatalogCreateTool
{
    protected function resource(): string
    {
        return 'tooling';
    }

    protected function model(): string
    {
        return Tool::class;
    }

    protected function noun(): string
    {
        return 'tool';
    }

    protected function attributes(): array
    {
        return ['name', 'cost', 'unit', 'notes'];
    }

    protected function fields(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The tool\'s name, e.g. "1/4in cobalt drill bit".')->required(),
            'cuts' => $schema->array()->items($schema->integer())->description('Ids of the material types it is rated to cut (search-catalog type=material-type). At least one.')->required(),
            'cost' => $schema->number()->description('What one use costs, in dollars.')->required(),
            'unit' => $schema->string()->description('What the cost is per, e.g. "each", "hole", "cut".')->required(),
            'notes' => $schema->string(),
        ];
    }

    protected function rules(Shop $shop): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cuts' => ['required', 'array', 'min:1'],
            'cuts.*' => ['distinct', Rule::exists('material_types', 'id')->where('shop_id', $shop->id)],
            'cost' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'unit' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function present(Model $record): array
    {
        return [...$record->only(['id', 'name', 'cost', 'unit', 'notes']), 'cuts' => $record->materialTypes()->pluck('material_types.name')->all()];
    }

    protected function afterCreate(Model $record, array $data): void
    {
        $record->materialTypes()->sync($data['cuts']);
    }
}
