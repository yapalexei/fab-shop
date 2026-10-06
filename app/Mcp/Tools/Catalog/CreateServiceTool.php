<?php

namespace App\Mcp\Tools\Catalog;

use App\Models\Service;
use App\Models\Shop;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create-service')]
#[Description('Add an outsourced service to the catalog (e.g. powder coating). Supplier prices are set on the supplier, with create-business-partner / update-business-partner. Refuses a duplicate name unless allow_duplicate is true.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class CreateServiceTool extends CatalogCreateTool
{
    protected function resource(): string
    {
        return 'services';
    }

    protected function model(): string
    {
        return Service::class;
    }

    protected function noun(): string
    {
        return 'service';
    }

    protected function attributes(): array
    {
        return ['name', 'notes'];
    }

    protected function fields(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The service\'s name, e.g. "Powder coating".')->required(),
            'notes' => $schema->string(),
        ];
    }

    protected function rules(Shop $shop): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function present(Model $record): array
    {
        return $record->only(['id', 'name', 'notes']);
    }
}
