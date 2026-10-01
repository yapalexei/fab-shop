<?php

namespace App\Support;

use App\Models\Shop;
use Illuminate\Support\Facades\DB;

/**
 * The material types every shop starts with, and the cap on custom ones.
 * Types are per-shop records (App\Models\MaterialType) so a shop can add
 * its own - e.g. "Chromoly (4130)" or "Galvanized steel" - on top of these.
 */
final class MaterialTypes
{
    public const DEFAULTS = [
        'Carbon steel',
        'Stainless steel',
        'Aluminum',
        'Cast iron',
        'Copper & brass',
        'Titanium',
        'Plastic',
        'Wood',
    ];

    public static function customLimit(): int
    {
        return config('shop.max_custom_material_types');
    }

    /**
     * Give a shop any built-in types it doesn't have yet. Idempotent, so
     * adding a name to DEFAULTS later just needs this run for every shop.
     *
     * @param  array<int, string>|null  $only  limit to these names (case-insensitive); null adds all
     * @return int how many were added
     */
    public static function seedDefaults(Shop $shop, ?array $only = null): int
    {
        $existing = DB::table('material_types')
            ->where('shop_id', $shop->id)
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();

        $only = $only === null ? null : array_map('mb_strtolower', $only);

        $rows = collect(self::DEFAULTS)
            ->filter(fn (string $name) => $only === null || in_array(mb_strtolower($name), $only, true))
            ->reject(fn (string $name) => in_array(mb_strtolower($name), $existing, true))
            ->map(fn (string $name) => [
                'shop_id' => $shop->id,
                'name' => $name,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        DB::table('material_types')->insert($rows);

        return count($rows);
    }
}
