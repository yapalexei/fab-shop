<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quote_line_items', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->after('quote_id')->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->after('material_id')->constrained()->nullOnDelete();
            $table->foreignId('business_partner_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('business_partner_id')->constrained()->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
            $table->dropConstrainedForeignId('service_id');
            $table->dropConstrainedForeignId('business_partner_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('equipment_id');
        });
    }
};
