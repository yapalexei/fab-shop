<?php

namespace App\Support\CsvImport;

use App\Enums\Feature;
use App\Models\MaterialType;
use App\Models\Shop;
use App\Models\Tool;
use App\Support\SupplierPrices;
use Illuminate\Database\Eloquent\Model;

class ToolImporter extends ResourceImporter
{
    public function key(): string
    {
        return 'tools';
    }

    public function label(): string
    {
        return 'Tooling';
    }

    public function modelClass(): string
    {
        return Tool::class;
    }

    public function feature(): Feature
    {
        return Feature::QuoteBuilder;
    }

    public function templateColumns(): array
    {
        return ['name', 'cuts', 'cost', 'unit', 'notes', 'part_number', 'source_url'];
    }

    public function requiredColumns(): array
    {
        return ['name', 'cuts', 'cost', 'unit'];
    }

    public function resolveRow(array $row, Shop $shop): array
    {
        $errors = [];
        $typeIds = [];

        $names = array_filter(array_map('trim', explode(';', $row['cuts'])), fn (string $n) => $n !== '');

        if ($names === []) {
            $errors[] = 'Pick at least one material this tool can cut (semicolon-separated in the "cuts" column).';
        }

        foreach ($names as $name) {
            $type = MaterialType::query()
                ->where('shop_id', $shop->id)
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($type) {
                $typeIds[] = $type->id;
            } else {
                $errors[] = "Material type \"{$name}\" doesn't exist - add it under Setup > Material types first.";
            }
        }

        return [
            'attributes' => [
                'name' => $row['name'],
                'cost' => $row['cost'] !== '' ? $row['cost'] : null,
                'unit' => $row['unit'] ?: null,
                'notes' => $row['notes'] ?: null,
                'part_number' => $row['part_number'] ?: null,
                'source_url' => $row['source_url'] ?: null,
            ],
            'relations' => ['materialTypes' => array_unique($typeIds)],
            'errors' => $errors,
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...SupplierPrices::LINK_RULES,
        ];
    }

    public function applyRelations(Model $model, array $relations): void
    {
        $model->materialTypes()->sync($relations['materialTypes'] ?? []);

        // A new tool, or one whose cost the file changed, has a freshly
        // confirmed price (same rule as the tool form).
        if ($model->wasRecentlyCreated || $model->wasChanged('cost')) {
            $model->forceFill(['price_checked_at' => now()])->save();
        }
    }
}
