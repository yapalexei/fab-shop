<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            // Nullable/nullOnDelete like every other historical-record user
            // reference (JobMaterialUsage::recorded_by) - removing a team
            // member shouldn't be blocked by jobs they were once assigned.
            $table->foreignId('assigned_to')->nullable()->after('job_stage_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });
    }
};
