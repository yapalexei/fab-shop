<?php

namespace App\Support\CsvImport;

/**
 * Guesses which file column feeds each template column, so the mapping step
 * opens pre-filled. A header matches on its normalized spelling ("Material
 * Type" = material_type) or a common synonym from a supplier export; the
 * owner can always change a guess.
 */
class ColumnMapper
{
    /** @var array<string, array<int, string>> */
    private const SYNONYMS = [
        'name' => ['description', 'item', 'item_name', 'product', 'product_name', 'title'],
        'unit' => ['uom', 'unit_of_measure', 'units', 'sold_per', 'per'],
        'notes' => ['note', 'comments', 'comment', 'remarks'],
        'email' => ['e_mail', 'email_address'],
        'phone' => ['telephone', 'phone_number', 'tel'],
        'address' => ['street_address'],
        'cost' => ['price', 'unit_price', 'unit_cost'],
        'rate' => ['price', 'hourly_rate'],
        'material_type' => ['type', 'material', 'category'],
    ];

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $templateColumns
     * @return array<string, int|null> template column => file column index
     */
    public static function suggest(array $headers, array $templateColumns): array
    {
        $normalized = array_map(self::normalize(...), $headers);
        $taken = [];
        $suggestion = array_fill_keys($templateColumns, null);

        // Exact spellings first, so a synonym never steals a column that
        // another template column matches exactly.
        foreach ([false, true] as $useSynonyms) {
            foreach ($templateColumns as $column) {
                if ($suggestion[$column] !== null) {
                    continue;
                }

                $candidates = $useSynonyms ? (self::SYNONYMS[$column] ?? []) : [self::normalize($column)];

                foreach ($candidates as $candidate) {
                    $index = array_search($candidate, $normalized, true);

                    if ($index !== false && ! isset($taken[$index])) {
                        $suggestion[$column] = $index;
                        $taken[$index] = true;

                        break;
                    }
                }
            }
        }

        return $suggestion;
    }

    private static function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value)), '_');
    }
}
