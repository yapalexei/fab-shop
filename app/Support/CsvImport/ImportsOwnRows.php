<?php

namespace App\Support\CsvImport;

use App\Models\Shop;

/**
 * An importer whose rows aren't "one record per name" (a supplier's price
 * list adds or updates rows on a supplier's price table), so it applies the
 * whole file itself instead of going through the shared name-matching loop.
 */
interface ImportsOwnRows
{
    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, mixed>  $options
     * @return array{0: int, 1: int, 2: int, 3: array<int, array{row: int, messages: array<int, string>}>} created, updated, skipped, errors
     */
    public function process(array $rows, Shop $shop, array $options, bool $overwrite): array;
}
