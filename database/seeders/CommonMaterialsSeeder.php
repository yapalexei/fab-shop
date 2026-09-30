<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Support\MaterialTypes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A starter catalog of materials common in metal fabrication (plus wood and
 * plastics), filed under the built-in material types. Names are alloy/grade
 * level - the stock form (bar, sheet, tube...) and supplier price are what a
 * shop adds per supplier.
 *
 * Runs for every shop, idempotently (matched by case-insensitive name), so
 * it can also be run on its own against an existing database:
 *   sail artisan db:seed --class=CommonMaterialsSeeder
 * Shop rows are read and written directly: there's no authenticated user
 * here, so the tenant global scope doesn't apply.
 */
class CommonMaterialsSeeder extends Seeder
{
    /**
     * [material type, name, unit, notes]. Type names must match
     * App\Support\MaterialTypes::DEFAULTS.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public const MATERIALS = [
        // Carbon steel
        ['Carbon steel', '1018 cold-rolled steel', 'lb', 'General-purpose low-carbon steel; bar, flat and square stock. Welds and machines easily.'],
        ['Carbon steel', 'A36 hot-rolled steel', 'lb', 'Structural steel for plate, angle, channel and beam. The default for frames and brackets.'],
        ['Carbon steel', '1045 steel', 'lb', 'Medium-carbon steel for shafts, pins and gears; heat-treatable.'],
        ['Carbon steel', '4130 chromoly', 'lb', 'Alloy steel tube and sheet for roll cages, frames and aircraft parts. Needs care when welding.'],
        ['Carbon steel', '4140 alloy steel', 'lb', 'High-strength alloy bar for shafts, tooling and heavy-duty parts.'],
        ['Carbon steel', 'A500 structural tube', 'ft', 'Square, rectangular and round structural tubing (HSS).'],
        ['Carbon steel', 'A53 pipe', 'ft', 'Black steel pipe for rails, handrails and structural use.'],
        ['Carbon steel', 'A572 Grade 50 plate', 'lb', 'High-strength low-alloy structural plate.'],
        ['Carbon steel', '11 gauge hot-rolled steel sheet', 'sheet', 'Common sheet steel for enclosures, panels and signs (P&O).'],
        ['Carbon steel', '16 gauge cold-rolled steel sheet', 'sheet', 'Smooth finish sheet steel for cabinets and painted parts.'],
        ['Carbon steel', 'Galvanized steel sheet (G90)', 'sheet', 'Zinc-coated sheet for ductwork, roofing and outdoor parts.'],
        ['Carbon steel', 'Steel tread plate', 'sheet', 'Raised-pattern (diamond) plate for floors, steps and truck beds.'],
        ['Carbon steel', 'Rebar', 'ft', 'Reinforcing bar, often used for decorative and ornamental work.'],

        // Stainless steel
        ['Stainless steel', '304 stainless steel', 'lb', 'The standard austenitic stainless; food service, architectural, general fabrication.'],
        ['Stainless steel', '316 stainless steel', 'lb', 'Marine-grade stainless with better corrosion resistance than 304.'],
        ['Stainless steel', '316L stainless steel', 'lb', 'Low-carbon 316 for welded parts in corrosive environments.'],
        ['Stainless steel', '410 stainless steel', 'lb', 'Hardenable martensitic stainless for shafts and cutlery.'],
        ['Stainless steel', '430 stainless steel', 'lb', 'Magnetic ferritic stainless; appliances and trim.'],
        ['Stainless steel', '17-4 PH stainless steel', 'lb', 'Precipitation-hardening stainless with high strength.'],
        ['Stainless steel', '2205 duplex stainless', 'lb', 'High-strength, corrosion-resistant duplex stainless.'],

        // Aluminum
        ['Aluminum', '6061-T6 aluminum', 'lb', 'The most common structural aluminum; plate, bar, tube and angle. Weldable and machinable.'],
        ['Aluminum', '6063-T5 aluminum', 'ft', 'Architectural extrusions, tube and channel with a good finish.'],
        ['Aluminum', '5052-H32 aluminum', 'sheet', 'Sheet aluminum that forms and welds well; marine and enclosures.'],
        ['Aluminum', '3003-H14 aluminum', 'sheet', 'General-purpose sheet with good formability.'],
        ['Aluminum', '5083 aluminum plate', 'lb', 'Marine-grade plate with excellent weld strength and seawater resistance.'],
        ['Aluminum', '7075-T6 aluminum', 'lb', 'Very high-strength aluminum for aerospace and tooling; not weldable.'],
        ['Aluminum', '2024-T3 aluminum', 'lb', 'High-strength aerospace aluminum; not readily weldable.'],
        ['Aluminum', 'Aluminum tread plate', 'sheet', 'Diamond-pattern plate for trailers, toolboxes and steps.'],
        ['Aluminum', 'MIC-6 cast aluminum plate', 'lb', 'Stress-relieved cast tooling plate that stays flat when machined.'],

        // Cast iron
        ['Cast iron', 'Gray cast iron (Class 30)', 'lb', 'Machinable, vibration-damping cast iron for bases and housings.'],
        ['Cast iron', 'Ductile iron (65-45-12)', 'lb', 'Stronger, more ductile cast iron for gears and fittings.'],

        // Copper & brass
        ['Copper & brass', 'C110 copper', 'lb', 'Electrolytic tough pitch copper for electrical and thermal parts.'],
        ['Copper & brass', 'C260 cartridge brass', 'sheet', 'Sheet brass for decorative work and forming.'],
        ['Copper & brass', 'C360 free-machining brass', 'lb', 'Brass bar that machines cleanly; fittings and fixtures.'],
        ['Copper & brass', 'C510 phosphor bronze', 'lb', 'Springs, bushings and wear parts.'],
        ['Copper & brass', 'C932 bearing bronze', 'lb', 'Bushings and bearings; SAE 660.'],

        // Titanium
        ['Titanium', 'Titanium Grade 2', 'lb', 'Commercially pure titanium with excellent corrosion resistance.'],
        ['Titanium', 'Titanium Grade 5 (Ti-6Al-4V)', 'lb', 'The standard high-strength titanium alloy for aerospace and performance parts.'],

        // Plastic
        ['Plastic', 'HDPE', 'sheet', 'High-density polyethylene; cutting boards, liners and chemical tanks.'],
        ['Plastic', 'UHMW polyethylene', 'sheet', 'Very low friction and wear-resistant; wear strips and chutes.'],
        ['Plastic', 'Acetal (Delrin)', 'lb', 'Stiff, machinable plastic for gears and bushings.'],
        ['Plastic', 'Nylon 6/6', 'lb', 'Tough engineering plastic for gears, rollers and wear parts.'],
        ['Plastic', 'Acrylic', 'sheet', 'Clear rigid sheet for guards, windows and displays.'],
        ['Plastic', 'Polycarbonate', 'sheet', 'Impact-resistant clear sheet for machine guards.'],
        ['Plastic', 'PVC', 'sheet', 'Rigid sheet for chemical-resistant panels and signs.'],

        // Wood
        ['Wood', 'Baltic birch plywood', 'sheet', 'Void-free multi-ply birch; jigs, fixtures, cabinets and laser cutting.'],
        ['Wood', 'Birch', 'bd ft', 'Hardwood lumber with a fine, even grain.'],
        ['Wood', 'Red oak', 'bd ft', 'Common hardwood for furniture, tabletops and trim.'],
        ['Wood', 'White oak', 'bd ft', 'Dense, rot-resistant hardwood for outdoor and furniture use.'],
        ['Wood', 'Hard maple', 'bd ft', 'Very hard, light-colored hardwood for butcher blocks and work surfaces.'],
        ['Wood', 'Walnut', 'bd ft', 'Dark premium hardwood for furniture and accents.'],
        ['Wood', 'Cherry', 'bd ft', 'Fine-grained hardwood that darkens with age.'],
        ['Wood', 'Ash', 'bd ft', 'Strong, flexible hardwood; handles and frames.'],
        ['Wood', 'Poplar', 'bd ft', 'Inexpensive, easily worked hardwood; painted parts.'],
        ['Wood', 'Southern yellow pine', 'bd ft', 'Construction softwood; framing, braces and crates.'],
        ['Wood', 'Douglas fir', 'bd ft', 'Strong softwood for framing and heavy timber.'],
        ['Wood', 'Cedar', 'bd ft', 'Rot-resistant softwood for outdoor work.'],
        ['Wood', 'MDF', 'sheet', 'Smooth, stable sheet for templates, jigs and paint-grade parts.'],
        ['Wood', 'Plywood (CDX)', 'sheet', 'Construction-grade plywood for sheathing, crates and templates.'],
    ];

    public function run(): void
    {
        foreach (Shop::query()->get() as $shop) {
            self::seedShop($shop);
        }
    }

    public static function seedShop(Shop $shop): void
    {
        MaterialTypes::seedDefaults($shop);

        $typeIds = DB::table('material_types')
            ->where('shop_id', $shop->id)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);

        $existing = DB::table('materials')
            ->where('shop_id', $shop->id)
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();

        $rows = collect(self::MATERIALS)
            ->reject(fn (array $m) => in_array(mb_strtolower($m[1]), $existing, true))
            ->map(fn (array $m) => [
                'shop_id' => $shop->id,
                'material_type_id' => $typeIds[mb_strtolower($m[0])] ?? null,
                'name' => $m[1],
                'unit' => $m[2],
                'notes' => $m[3],
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        DB::table('materials')->insert($rows);
    }
}
