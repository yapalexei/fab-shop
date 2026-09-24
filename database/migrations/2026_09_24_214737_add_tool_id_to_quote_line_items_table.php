<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Null on delete, like the other catalog links: removing a tool from
        // the catalog leaves past quote lines with their description and price.
        Schema::table('quote_line_items', function (Blueprint $table) {
            $table->foreignId('tool_id')->nullable()->after('equipment_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quote_line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tool_id');
        });
    }
};
