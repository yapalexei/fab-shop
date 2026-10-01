<?php

namespace App\Support;

use App\Models\Shop;
use Database\Seeders\CommonMaterialsSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Starter data a shop can pre-load from the UI (the "Load sample ..."
 * buttons): material types, materials, outsourced services and shop
 * equipment. Every loader is idempotent - anything a shop already has, by
 * case-insensitive name, is left alone - and returns how many rows it added.
 *
 * Equipment rates are ballpark hourly figures to be adjusted to the shop's
 * own costs, not recommendations. Rows are written directly with an explicit
 * shop_id, so these also work with no authenticated user.
 */
final class SampleCatalog
{
    /** Resource key (as used in the URL) => table. */
    public const TABLES = [
        'material-types' => 'material_types',
        'materials' => 'materials',
        'services' => 'services',
        'equipment' => 'equipment',
    ];

    /** Common specialty types beyond the built-ins (these count as custom ones). */
    public const MATERIAL_TYPES = [
        'Chromoly (4130)',
        'Galvanized steel',
        'Tool steel',
        'Weathering steel (Corten)',
        'Nickel alloys',
        'Composites',
    ];

    /**
     * Work a business partner typically does on the shop's behalf.
     *
     * @var array<string, string> name => notes
     */
    public const SERVICES = [
        'Anodizing (Type II)' => 'Decorative and corrosion-resistant anodized finish for aluminum; available in colors.',
        'Hard anodizing (Type III)' => 'Thick, wear-resistant anodized coating for aluminum.',
        'Powder coating' => 'Baked-on durable finish for steel and aluminum; wide color range.',
        'Wet paint / industrial coating' => 'Primer and topcoat spray finishes, including epoxy and polyurethane.',
        'Hot-dip galvanizing' => 'Zinc coating for outdoor structural steel.',
        'Zinc plating' => 'Thin electroplated zinc for fasteners and small parts.',
        'Nickel / chrome plating' => 'Decorative or hard plated finishes.',
        'Black oxide' => 'Conversion coating giving a black finish with mild corrosion resistance.',
        'Passivation' => 'Chemical treatment restoring corrosion resistance to stainless steel.',
        'Electropolishing' => 'Smooths and brightens stainless steel surfaces.',
        'Heat treating' => 'Hardening, tempering, annealing and case hardening.',
        'Stress relieving' => 'Thermal treatment to relieve stress in welded or machined parts.',
        'Sandblasting / media blasting' => 'Abrasive cleaning and surface prep before coating.',
        'Tumbling & deburring' => 'Vibratory finishing to remove burrs and sharp edges.',
        'Waterjet cutting' => 'Cold cutting of metal, stone, glass and composites with no heat-affected zone.',
        'Laser cutting' => 'Precision profile cutting of sheet and plate.',
        'Plasma cutting' => 'Fast cutting of conductive plate.',
        'CNC machining' => 'Outsourced milling and turning of precision parts.',
        'Wire EDM' => 'Precision cutting of hard or complex shapes.',
        'Press brake forming' => 'Bending sheet and plate to angle.',
        'Plate rolling' => 'Rolling plate, pipe and tube to a radius.',
        'Surface grinding' => 'Flat, precision ground surfaces.',
        'Shot peening' => 'Surface treatment to improve fatigue life.',
        'NDT inspection' => 'Dye penetrant, magnetic particle, ultrasonic or X-ray inspection of welds and parts.',
        'Laser engraving / marking' => 'Part numbers, logos and serial marking.',
        'Delivery / freight' => 'Trucking or courier for finished work.',
    ];

    /**
     * [name, hourly rate, notes]. Hourly rates are placeholders to edit.
     *
     * @var array<int, array{0: string, 1: float, 2: string}>
     */
    public const EQUIPMENT = [
        ['Fiber laser cutter', 120, 'Sheet and plate cutting; fast on thin metals, including reflective ones.'],
        ['CO2 laser cutter', 85, 'Cuts and engraves wood, acrylic and thin sheet.'],
        ['CNC plasma table', 90, 'Plate and sheet cutting.'],
        ['Waterjet', 140, 'Cuts almost any material without heat distortion.'],
        ['CNC router', 65, 'Wood, plastics and aluminum sheet.'],
        ['CNC mill (VMC)', 95, 'Vertical machining center.'],
        ['CNC lathe', 85, 'Turning center.'],
        ['Manual mill', 55, 'Knee mill for one-off and repair work.'],
        ['Manual lathe', 50, 'Engine lathe for one-off and repair work.'],
        ['Press brake', 80, 'Forming sheet and plate.'],
        ['Hydraulic shear', 50, 'Straight cuts in sheet.'],
        ['Ironworker', 55, 'Punching, shearing and notching.'],
        ['Plate roll', 55, 'Rolling plate and sheet to a radius.'],
        ['Tube bender', 40, 'Bending tube and pipe.'],
        ['Band saw', 35, 'Cutting bar, tube and structural shapes.'],
        ['Cold saw', 45, 'Cutting tube and bar with a clean, square cut.'],
        ['Drill press', 30, 'Drilling and tapping.'],
        ['MIG welder', 45, 'Wire-feed welding for steel and aluminum.'],
        ['TIG welder', 55, 'Precision welding of stainless, aluminum and thin material.'],
        ['Sandblasting cabinet', 35, 'Surface prep and finishing of small parts.'],
        ['3D printer (Bambu Lab X1C)', 8, 'Fixtures, jigs, prototypes and plastic parts.'],
    ];

    /**
     * What the "Load sample ..." dialog offers for a resource: every sample
     * item, with whether the shop already has it (those are shown but not
     * added again).
     *
     * @return array<int, array{name: string, group: string|null, detail: string|null, exists: bool}>
     */
    public static function options(string $resource, Shop $shop): array
    {
        $table = self::TABLES[$resource];
        $existing = self::existingNames($table, $shop);

        return collect(self::catalog($resource))
            ->map(fn (array $item) => [...$item, 'exists' => in_array(mb_strtolower($item['name']), $existing, true)])
            ->values()
            ->all();
    }

    /**
     * Add the chosen sample items (by name) to the shop; null adds the lot.
     *
     * @param  array<int, string>|null  $only
     * @return int how many were added
     */
    public static function load(string $resource, Shop $shop, ?array $only = null): int
    {
        return match ($resource) {
            'material-types' => self::materialTypes($shop, $only),
            'materials' => self::materials($shop, $only),
            'services' => self::services($shop, $only),
            'equipment' => self::equipment($shop, $only),
        };
    }

    /**
     * @return array<int, array{name: string, group: string|null, detail: string|null}>
     */
    public static function catalog(string $resource): array
    {
        return match ($resource) {
            'material-types' => [
                ...array_map(fn (string $name) => ['name' => $name, 'group' => 'Built-in', 'detail' => null], MaterialTypes::DEFAULTS),
                ...array_map(fn (string $name) => ['name' => $name, 'group' => 'Specialty', 'detail' => 'Counts toward your custom type limit'], self::MATERIAL_TYPES),
            ],
            'materials' => array_map(fn (array $m) => ['name' => $m[1], 'group' => $m[0], 'detail' => $m[3]], CommonMaterialsSeeder::MATERIALS),
            'services' => array_map(fn (string $name, string $notes) => ['name' => $name, 'group' => null, 'detail' => $notes], array_keys(self::SERVICES), self::SERVICES),
            'equipment' => array_map(fn (array $e) => ['name' => $e[0], 'group' => null, 'detail' => '$'.$e[1].'/hr (ballpark) - '.$e[2]], self::EQUIPMENT),
        };
    }

    /** @param  array<int, string>|null  $only */
    public static function materialTypes(Shop $shop, ?array $only = null): int
    {
        $added = MaterialTypes::seedDefaults($shop, $only);
        $only = $only === null ? null : array_map('mb_strtolower', $only);

        $existing = DB::table('material_types')->where('shop_id', $shop->id)->get(['name', 'is_default']);
        $names = $existing->pluck('name')->map(fn (string $n) => mb_strtolower($n))->all();
        // Custom types are capped per shop; stop at the limit rather than fail.
        $room = max(0, MaterialTypes::customLimit() - $existing->where('is_default', false)->count());

        $rows = collect(self::MATERIAL_TYPES)
            ->filter(fn (string $name) => $only === null || in_array(mb_strtolower($name), $only, true))
            ->reject(fn (string $name) => in_array(mb_strtolower($name), $names, true))
            ->take($room)
            ->map(fn (string $name) => [
                'shop_id' => $shop->id,
                'name' => $name,
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        DB::table('material_types')->insert($rows);

        return $added + count($rows);
    }

    /**
     * Materials are filed under the built-in material types, so those are
     * restored if missing.
     *
     * @param  array<int, string>|null  $only
     */
    public static function materials(Shop $shop, ?array $only = null): int
    {
        return CommonMaterialsSeeder::seedShop($shop, $only);
    }

    /** @param  array<int, string>|null  $only */
    public static function services(Shop $shop, ?array $only = null): int
    {
        $existing = self::existingNames('services', $shop);
        $only = $only === null ? null : array_map('mb_strtolower', $only);

        $rows = collect(self::SERVICES)
            ->filter(fn (string $notes, string $name) => $only === null || in_array(mb_strtolower($name), $only, true))
            ->reject(fn (string $notes, string $name) => in_array(mb_strtolower($name), $existing, true))
            ->map(fn (string $notes, string $name) => [
                'shop_id' => $shop->id,
                'name' => $name,
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        DB::table('services')->insert($rows);

        return count($rows);
    }

    /** @param  array<int, string>|null  $only */
    public static function equipment(Shop $shop, ?array $only = null): int
    {
        $existing = self::existingNames('equipment', $shop);
        $only = $only === null ? null : array_map('mb_strtolower', $only);

        $rows = collect(self::EQUIPMENT)
            ->filter(fn (array $item) => $only === null || in_array(mb_strtolower($item[0]), $only, true))
            ->reject(fn (array $item) => in_array(mb_strtolower($item[0]), $existing, true))
            ->map(fn (array $item) => [
                'shop_id' => $shop->id,
                'name' => $item[0],
                'billing_method' => 'hourly',
                'rate' => $item[1],
                'notes' => $item[2],
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        DB::table('equipment')->insert($rows);

        return count($rows);
    }

    /**
     * @return array<int, string>
     */
    private static function existingNames(string $table, Shop $shop): array
    {
        return DB::table($table)->where('shop_id', $shop->id)->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name))->all();
    }
}
