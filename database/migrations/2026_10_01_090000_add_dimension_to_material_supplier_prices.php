<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A supplier can price the same material at several sizes (4 ft vs 4 in
     * of bar stock cost very different amounts), so the pivot allows more
     * than one row per material+supplier, told apart by an optional
     * `dimension`. A quote line records the size it was priced at.
     */
    public function up(): void
    {
        Schema::table('material_business_partner', function (Blueprint $table) {
            $table->dropUnique(['material_id', 'business_partner_id']);
            $table->string('dimension', 100)->nullable()->after('business_partner_id');
        });

        // One price per material + supplier + size; "no size" counts as a size
        // (a plain NULL would let duplicates through), and case doesn't matter.
        DB::statement('CREATE UNIQUE INDEX material_business_partner_size_unique ON material_business_partner (material_id, business_partner_id, (COALESCE(LOWER(dimension), \'\')))');

        Schema::table('quote_line_items', function (Blueprint $table) {
            $table->string('material_dimension', 100)->nullable()->after('business_partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('quote_line_items', fn (Blueprint $table) => $table->dropColumn('material_dimension'));

        DB::statement('DROP INDEX IF EXISTS material_business_partner_size_unique');

        // Keep the cheapest row per pair before restoring the old unique key.
        DB::statement('DELETE FROM material_business_partner a USING material_business_partner b WHERE a.material_id = b.material_id AND a.business_partner_id = b.business_partner_id AND (a.price > b.price OR (a.price = b.price AND a.id > b.id))');

        Schema::table('material_business_partner', function (Blueprint $table) {
            $table->dropColumn('dimension');
            $table->unique(['material_id', 'business_partner_id']);
        });
    }
};
