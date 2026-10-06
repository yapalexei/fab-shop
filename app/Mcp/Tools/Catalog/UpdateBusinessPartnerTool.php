<?php

namespace App\Mcp\Tools\Catalog;

use App\Enums\BusinessPartnerType;
use App\Models\BusinessPartner;
use App\Models\Shop;
use App\Support\SupplierPrices;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('update-business-partner')]
#[Description('Change the details of a supplier, and/or replace the materials or services they supply and their prices. Send only what you want to change; sending materials or services REPLACES that whole list.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class UpdateBusinessPartnerTool extends CatalogUpdateTool
{
    protected function resource(): string
    {
        return 'business-partners';
    }

    protected function model(): string
    {
        return BusinessPartner::class;
    }

    protected function noun(): string
    {
        return 'business partner';
    }

    protected function attributes(): array
    {
        return ['name', 'email', 'phone', 'address', 'notes', 'type'];
    }

    protected function fields(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The supplier\'s name.')->required(),
            'email' => $schema->string(),
            'phone' => $schema->string(),
            'address' => $schema->string(),
            'notes' => $schema->string(),
            'type' => $schema->string()->enum(array_column(BusinessPartnerType::cases(), 'value'))->description('supplier (materials), service_provider (anodizing, powder coat, etc.) or both. Defaults to supplier.'),
            'materials' => $schema->array()->items($schema->object([
                'material_id' => $schema->integer()->description('A catalog material id.')->required(),
                'price' => $schema->number()->description('Their price for it, in dollars.')->required(),
                'dimension' => $schema->string()->description('The size this price is for, if they price sizes separately.'),
                'part_number' => $schema->string()->description('Their part number for it. Left as it was if not sent.'),
                'source_url' => $schema->string()->description('Link to the product page (http/https), for rechecking the price. Left as it was if not sent.'),
            ]))->description('Materials they supply, with prices. When sent on an update this REPLACES their whole list.'),
            'services' => $schema->array()->items($schema->object([
                'service_id' => $schema->integer()->description('A catalog service id.')->required(),
                'price' => $schema->number()->description('Their price for it, in dollars.')->required(),
            ]))->description('Services they provide, with prices. When sent on an update this REPLACES their whole list.'),
        ];
    }

    protected function rules(Shop $shop): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'type' => [Rule::enum(BusinessPartnerType::class)],
            'materials' => ['nullable', 'array'],
            'materials.*.material_id' => ['required', Rule::exists('materials', 'id')->where('shop_id', $shop->id)],
            'materials.*.dimension' => ['nullable', 'string', 'max:100'],
            'materials.*.price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'materials.*.part_number' => SupplierPrices::LINK_RULES['part_number'],
            'materials.*.source_url' => SupplierPrices::LINK_RULES['source_url'],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required', Rule::exists('services', 'id')->where('shop_id', $shop->id)],
            'services.*.price' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ];
    }

    protected function present(Model $record): array
    {
        return [
            ...$record->only(['id', 'name', 'email', 'phone', 'address', 'notes']),
            'type' => $record->type->value,
            'materials' => $record->materials()->get()->map(fn ($m) => ['material_id' => $m->id, 'name' => $m->name, 'dimension' => $m->pivot->dimension, 'price' => (float) $m->pivot->price, 'part_number' => $m->pivot->part_number, 'source_url' => $m->pivot->source_url])->all(),
            'services' => $record->services()->get()->map(fn ($s) => ['service_id' => $s->id, 'name' => $s->name, 'price' => (float) $s->pivot->price])->all(),
        ];
    }

    protected function applyRelations(Model $record, array $data, array $sent): array
    {
        $changed = [];

        if (in_array('materials', $sent, true)) {
            SupplierPrices::syncMaterials($record, $data['materials'] ?? []);
            $changed[] = 'materials';
        }

        if (in_array('services', $sent, true)) {
            $this->syncServices($record, $data['services'] ?? []);
            $changed[] = 'services';
        }

        return $changed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     */
    private function syncServices(Model $partner, array $services): void
    {
        $partner->services()->sync(collect($services)->mapWithKeys(
            fn (array $row) => [$row['service_id'] => ['price' => $row['price']]],
        ));
    }
}
