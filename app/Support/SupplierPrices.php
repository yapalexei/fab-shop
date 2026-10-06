<?php

namespace App\Support;

use App\Models\BusinessPartner;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A supplier's material price list and where each price came from (part
 * number, product page, when it was last confirmed).
 */
class SupplierPrices
{
    /** Validation for the link fields on a price row or a tool. */
    public const LINK_RULES = [
        'part_number' => ['nullable', 'string', 'max:100'],
        'source_url' => ['nullable', 'url:http,https', 'max:2048'],
    ];

    /**
     * Replace a supplier's material prices with `$rows`. A material can
     * appear several times (one row per size), which sync() can't express,
     * so the set is rewritten - but a row that matches an existing material
     * + size keeps its part number and link unless the new row sends its
     * own, and keeps its checked date unless the price changed or the row
     * says `mark_checked`. That is what lets the MCP tools, which know
     * nothing about links, edit prices without wiping them.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function syncMaterials(BusinessPartner $partner, array $rows): void
    {
        $existing = $partner->materials()->get()->keyBy(
            fn ($material) => self::key($material->id, $material->pivot->dimension),
        );

        $partner->materials()->detach();

        foreach ($rows as $row) {
            $dimension = filled($row['dimension'] ?? null) ? trim($row['dimension']) : null;
            $old = $existing->get(self::key($row['material_id'], $dimension))?->pivot;

            $priceChanged = ! $old || (float) $old->price !== (float) $row['price'];
            $checked = $priceChanged || ! empty($row['mark_checked']) ? now() : self::utc($old->price_checked_at);

            $partner->materials()->attach($row['material_id'], [
                'price' => $row['price'],
                'dimension' => $dimension,
                'part_number' => array_key_exists('part_number', $row) ? $row['part_number'] : $old?->part_number,
                'source_url' => array_key_exists('source_url', $row) ? $row['source_url'] : $old?->source_url,
                'price_checked_at' => $checked,
            ]);
        }
    }

    /**
     * A stored timestamp as an ISO string the frontend can show in the
     * shop's timezone (pivot columns come back as bare UTC wall-clock text).
     */
    public static function iso(CarbonInterface|string|null $value): ?string
    {
        return self::utc($value)?->toIso8601String();
    }

    private static function utc(CarbonInterface|string|null $value): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof CarbonInterface ? $value->copy()->utc() : Carbon::parse($value, 'UTC');
    }

    private static function key(int|string $materialId, ?string $dimension): string
    {
        return $materialId.'|'.mb_strtolower(trim((string) $dimension));
    }
}
