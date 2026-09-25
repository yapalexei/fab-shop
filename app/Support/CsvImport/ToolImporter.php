<?php

namespace App\Support\CsvImport;

use App\Enums\Feature;
use App\Models\MaterialType;
use App\Models\Shop;
use App\Models\Tool;
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
        return ['name', 'cuts', 'cost', 'unit', 'notes'];
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
        ];
    }

    public function applyRelations(Model $model, array $relations): void
    {
        $model->materialTypes()->sync($relations['materialTypes'] ?? []);
    }
}
