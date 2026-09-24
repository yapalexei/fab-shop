<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trial start and end are both fixed UTC instants recorded at signup, so
     * the trial is exactly N x 24h long no matter the shop's timezone - and
     * changing the timezone later can't stretch or shrink it.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('trial_started_at')->nullable()->after('plan_id');
        });

        DB::table('shops')->whereNotNull('trial_ends_at')->update(['trial_started_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('trial_started_at');
        });
    }
};
