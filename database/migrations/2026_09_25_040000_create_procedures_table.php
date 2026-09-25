<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A shop-defined process that people get qualified against - not
     * welding-specific (a WPS is one example, but so is "NDT dye
     * penetrant inspection" or "powder coat line operation"). Deliberately
     * no cascade from certifications/job_qualification_records: a procedure
     * still referenced by either can't be deleted, same reasoning as
     * material_types (the controller explains why before the FK would).
     */
    public function up(): void
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // An internal code or standard reference, e.g. "WPS-014" or
            // "SOP-3" - freeform, not validated against any real standard.
            $table->string('code', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Case-insensitive uniqueness per shop, same pattern as material_types.
        DB::statement('CREATE UNIQUE INDEX procedures_shop_id_lower_name_unique ON procedures (shop_id, lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
