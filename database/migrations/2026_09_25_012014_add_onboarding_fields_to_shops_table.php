<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Null onboarded_at means "not through the wizard yet" - the `onboarded`
     * middleware sends anyone in that state back into it. Every shop that
     * already exists was set up before onboarding existed, so it's
     * backfilled as already onboarded rather than retroactively locked out.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('onboarded_at')->nullable()->after('trial_ended_notified_at');
            // Which onboarding step to show next, while onboarded_at is
            // still null. Meaningless once onboarded_at is set.
            $table->string('onboarding_step')->nullable()->after('onboarded_at');
        });

        DB::table('shops')->update(['onboarded_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['onboarded_at', 'onboarding_step']);
        });
    }
};
