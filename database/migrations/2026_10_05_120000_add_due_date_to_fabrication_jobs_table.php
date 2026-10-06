<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A plain calendar date (no time, no timezone) - "due Friday" means the
     * same day everywhere, so it's deliberately not a UTC timestamp.
     */
    public function up(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
