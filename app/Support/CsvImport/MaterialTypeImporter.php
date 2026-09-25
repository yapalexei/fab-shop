<?php

namespace App\Support\CsvImport;

use App\Enums\Feature;
use App\Models\MaterialType;
use App\Models\Shop;
use App\Support\MaterialTypes;

class MaterialTypeImporter extends ResourceImporter
{
    public function key(): string
    {
        return 'material-types';
    }

    public function label(): string
    {
        return 'Material types';
    }

    public function modelClass(): string
    {
        return MaterialType::class;
    }

    public function feature(): Feature
    {
        return Feature::QuoteBuilder;
    }

    public function templateColumns(): array
    {
        return ['name'];
    }

    public function resolveRow(array $row, Shop $shop): array
    {
        return [
            'attributes' => ['name' => $row['name']],
            'relations' => [],
            'errors' => [],
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * Re-checked per row (not just once for the whole file), since each
     * newly-created row in this same run already counts by the time the
     * next one is considered - an import can't be used to end-run the cap
     * any more than adding them one at a time through the UI could.
     */
    public function beforeCreate(Shop $shop): ?string
    {
        $customCount = MaterialType::query()->where('shop_id', $shop->id)->where('is_default', false)->count();

        if ($customCount >= MaterialTypes::customLimit()) {
            return 'This shop already has the maximum of '.MaterialTypes::customLimit().' custom material types.';
        }

        return null;
    }
}
