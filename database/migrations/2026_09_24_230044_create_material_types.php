<?php

use App\Models\Shop;
use App\Support\MaterialTypes;
use App\Support\ShopPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The fixed list tools.cuts used to store, value => the built-in type
     * name it becomes. Inlined (not read from app code) so this migration
     * keeps meaning the same thing if the built-in list changes later.
     */
    private const LEGACY_CUTS = [
        'carbon-steel' => 'Carbon steel',
        'stainless-steel' => 'Stainless steel',
        'aluminum' => 'Aluminum',
        'cast-iron' => 'Cast iron',
        'copper-brass' => 'Copper & brass',
        'titanium' => 'Titanium',
        'plastic' => 'Plastic',
        'wood' => 'Wood',
    ];

    public function up(): void
    {
        Schema::create('material_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            // Built-ins come with every shop and can't be renamed/deleted;
            // custom ones count against the per-shop limit.
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Case-insensitive uniqueness per shop ("Aluminum" vs "aluminum").
        DB::statement('CREATE UNIQUE INDEX material_types_shop_id_lower_name_unique ON material_types (shop_id, lower(name))');

        // No cascade from the type side: a type still in use can't be
        // deleted (the app explains why before the database would refuse).
        Schema::create('material_type_tool', function (Blueprint $table) {
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_type_id')->constrained();
            $table->primary(['tool_id', 'material_type_id']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('material_type_id')->nullable()->after('shop_id')->constrained();
        });

        Shop::query()->each(fn (Shop $shop) => MaterialTypes::seedDefaults($shop));

        // Carry each tool's old fixed-list ratings over to its shop's types.
        DB::table('tools')->orderBy('id')->each(function ($tool) {
            $typeIds = DB::table('material_types')->where('shop_id', $tool->shop_id)->pluck('id', 'name');

            $rows = collect(json_decode($tool->cuts, true) ?? [])
                ->map(fn (string $value) => self::LEGACY_CUTS[$value] ?? null)
                ->filter(fn (?string $name) => $name !== null && isset($typeIds[$name]))
                ->map(fn (string $name) => ['tool_id' => $tool->id, 'material_type_id' => $typeIds[$name]])
                ->values()
                ->all();

            DB::table('material_type_tool')->insertOrIgnore($rows);
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn('cuts');
        });

        Shop::query()->each(fn (Shop $shop) => ShopPermissions::seedRoles($shop));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->jsonb('cuts')->default('[]');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_type_id');
        });

        Schema::dropIfExists('material_type_tool');
        Schema::dropIfExists('material_types');
    }
};
