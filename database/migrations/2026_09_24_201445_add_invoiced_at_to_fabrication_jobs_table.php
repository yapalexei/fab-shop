<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revenue on the dashboard is bucketed by when a job was invoiced, which
     * nothing recorded until now - backfill already-invoiced jobs with their
     * last update time, the closest existing approximation.
     */
    public function up(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->timestamp('invoiced_at')->nullable()->after('status');
        });

        DB::table('fabrication_jobs')
            ->where('status', 'invoiced')
            ->update(['invoiced_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->dropColumn('invoiced_at');
        });
    }
};
