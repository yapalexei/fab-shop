<?php

namespace App\Mcp\Tools\Catalog;

use App\Models\Material;
use App\Models\Shop;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create-material')]
#[Description('Add a material to the catalog (what the shop buys and quotes). Supplier prices are set on the supplier, with create-business-partner / update-business-partner. If a material with the same name exists this refuses unless allow_duplicate is true.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class CreateMaterialTool extends CatalogCreateTool
{
    protected function resource(): string
    {
        return 'materials';
    }

    protected function model(): string
    {
        return Material::class;
    }

    protected function noun(): string
    {
        return 'material';
    }

    protected function attributes(): array
    {
        return ['name', 'unit', 'notes', 'material_type_id'];
    }

    protected function fields(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The material\'s name, e.g. "1/4in steel plate".')->required(),
            'unit' => $schema->string()->description('What it is bought by, e.g. "sheet" or "ft".'),
            'material_type_id' => $schema->integer()->description('Its material type id (search-catalog type=material-type).'),
            'notes' => $schema->string(),
        ];
    }

    protected function rules(Shop $shop): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'material_type_id' => ['nullable', Rule::exists('material_types', 'id')->where('shop_id', $shop->id)],
        ];
    }

    protected function present(Model $record): array
    {
        return $record->only(['id', 'name', 'unit', 'material_type_id', 'notes']);
    }
}
