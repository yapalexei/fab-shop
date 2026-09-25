<?php

namespace App\Support\CsvImport;

use App\Enums\Feature;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Shop;

class MaterialImporter extends ResourceImporter
{
    public function key(): string
    {
        return 'materials';
    }

    public function label(): string
    {
        return 'Materials';
    }

    public function modelClass(): string
    {
        return Material::class;
    }

    public function feature(): Feature
    {
        return Feature::QuoteBuilder;
    }

    public function templateColumns(): array
    {
        return ['name', 'unit', 'material_type', 'notes'];
    }

    public function resolveRow(array $row, Shop $shop): array
    {
        $errors = [];
        $materialTypeId = null;

        if ($row['material_type'] !== '') {
            $type = MaterialType::query()
                ->where('shop_id', $shop->id)
                ->whereRaw('lower(name) = ?', [mb_strtolower($row['material_type'])])
                ->first();

            if ($type) {
                $materialTypeId = $type->id;
            } else {
                // Never auto-created - that would let an import silently
                // bypass the shop's custom-type cap.
                $errors[] = "Material type \"{$row['material_type']}\" doesn't exist - add it under Setup > Material types first.";
            }
        }

        return [
            'attributes' => [
                'name' => $row['name'],
                'unit' => $row['unit'] ?: null,
                'material_type_id' => $materialTypeId,
                'notes' => $row['notes'] ?: null,
            ],
            'relations' => [],
            'errors' => $errors,
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // material_type_id's existence is already confirmed in
            // resolveRow() - nothing further to check here.
            'material_type_id' => ['nullable', 'integer'],
        ];
    }
}
