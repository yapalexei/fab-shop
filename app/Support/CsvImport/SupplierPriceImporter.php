<?php

namespace App\Support\CsvImport;

use App\Enums\Feature;
use App\Models\BusinessPartner;
use App\Models\Material;
use App\Models\Shop;
use App\Support\SupplierPrices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * One supplier's price list - typically their own spreadsheet export - into
 * that supplier's material prices. Which supplier is chosen where the import
 * starts (their page), not in the file. A material is matched by name and
 * created when the shop doesn't have it yet; the price row is matched by
 * material + size.
 *
 * Importing a price list confirms those prices, so every row it adds or
 * overwrites is stamped as checked today. A row for a price already on file
 * is left alone unless "overwrite" is on. Console/queue context has no
 * authenticated user, so every query filters by shop_id explicitly.
 */
class SupplierPriceImporter extends ResourceImporter implements ImportsOwnRows
{
    public function key(): string
    {
        return 'supplier-prices';
    }

    public function label(): string
    {
        return 'Supplier prices';
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
        return ['material', 'size', 'price', 'unit', 'part_number', 'source_url'];
    }

    public function requiredColumns(): array
    {
        return ['material', 'price'];
    }

    public function resolveRow(array $row, Shop $shop): array
    {
        return [
            'attributes' => [
                'name' => $row['material'],
                'dimension' => $row['size'] ?: null,
                'price' => $row['price'] !== '' ? str_replace(['$', ','], '', $row['price']) : null,
                'unit' => $row['unit'] ?: null,
                'part_number' => $row['part_number'] ?: null,
                'source_url' => $row['source_url'] ?: null,
            ],
            'relations' => [],
            'errors' => [],
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'dimension' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'unit' => ['nullable', 'string', 'max:50'],
            ...SupplierPrices::LINK_RULES,
        ];
    }

    public function process(array $rows, Shop $shop, array $options, bool $overwrite): array
    {
        $partner = BusinessPartner::query()
            ->where('shop_id', $shop->id)
            ->find($options['business_partner_id'] ?? 0);

        if (! $partner) {
            return [0, 0, 0, [['row' => 0, 'messages' => ['That supplier no longer exists.']]]];
        }

        $created = $updated = $skipped = 0;
        $errors = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header is row 1

            $resolved = $this->resolveRow($row, $shop);
            $validator = Validator::make($resolved['attributes'], $this->rules());

            if ($validator->fails()) {
                $errors[] = ['row' => $rowNumber, 'messages' => $validator->errors()->all()];

                continue;
            }

            $attributes = $resolved['attributes'];
            $key = mb_strtolower($attributes['name']).'|'.mb_strtolower((string) $attributes['dimension']);

            if (isset($seen[$key])) {
                $errors[] = ['row' => $rowNumber, 'messages' => ["Already listed in row {$seen[$key]} at this size - only the first is used."]];

                continue;
            }

            $seen[$key] = $rowNumber;

            $material = Material::query()
                ->where('shop_id', $shop->id)
                ->whereRaw('lower(name) = ?', [mb_strtolower($attributes['name'])])
                ->first();

            $existing = $material ? $this->existingPrice($partner, $material, $attributes['dimension']) : false;

            if ($existing && ! $overwrite) {
                $skipped++;

                continue;
            }

            $material ??= tap(new Material, fn (Material $new) => $new->forceFill([
                'shop_id' => $shop->id,
                'name' => $attributes['name'],
                'unit' => $attributes['unit'],
            ])->save());

            $this->writePrice($partner, $material, $attributes, (bool) $existing);
            $existing ? $updated++ : $created++;
        }

        return [$created, $updated, $skipped, $errors];
    }

    private function existingPrice(BusinessPartner $partner, Material $material, ?string $dimension): bool
    {
        return $partner->materials()
            ->where('materials.id', $material->id)
            ->whereRaw("COALESCE(LOWER(material_business_partner.dimension), '') = ?", [mb_strtolower((string) $dimension)])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function writePrice(BusinessPartner $partner, Material $material, array $attributes, bool $exists): void
    {
        $pivot = [
            'price' => $attributes['price'],
            'part_number' => $attributes['part_number'],
            'source_url' => $attributes['source_url'],
            'price_checked_at' => now(),
        ];

        if (! $exists) {
            $partner->materials()->attach($material->id, ['dimension' => $attributes['dimension'], ...$pivot]);

            return;
        }

        // Straight on the pivot table: a material can have several rows per
        // supplier (one per size), which updateExistingPivot() can't tell apart.
        DB::table('material_business_partner')
            ->where('material_id', $material->id)
            ->where('business_partner_id', $partner->id)
            ->whereRaw("COALESCE(LOWER(dimension), '') = ?", [mb_strtolower((string) $attributes['dimension'])])
            ->update([...$pivot, 'updated_at' => now()]);
    }
}
