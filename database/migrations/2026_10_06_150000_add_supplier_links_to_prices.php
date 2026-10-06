<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a price came from, so it can be rechecked later: the supplier's
     * part number, the product page, and when the price was last confirmed.
     * A material's price belongs to the supplier row (each supplier lists its
     * own part number and page); a tool carries its own cost, so it carries
     * the link too. Existing prices count as checked when they were saved.
     */
    public function up(): void
    {
        foreach (['material_business_partner', 'tools'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('part_number', 100)->nullable();
                $table->string('source_url', 2048)->nullable();
                $table->timestamp('price_checked_at')->nullable();
            });

            DB::statement("UPDATE {$name} SET price_checked_at = updated_at");
        }
    }

    public function down(): void
    {
        foreach (['material_business_partner', 'tools'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['part_number', 'source_url', 'price_checked_at']));
        }
    }
};
