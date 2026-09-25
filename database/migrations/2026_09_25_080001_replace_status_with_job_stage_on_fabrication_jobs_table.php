<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the fixed JobStatus enum column with a job_stage_id pointing
     * at the shop's own configurable pipeline (see job_stages migration),
     * plus a cancelled_at flag - cancellation is no longer a rung on the
     * ladder (a JobStage row), it's a side flag, same shape as suspended_at/
     * onboarded_at elsewhere on this app.
     */
    public function up(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->foreignId('job_stage_id')->nullable()->after('status')->constrained()->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('job_stage_id');
        });

        // Map every existing job's fixed status onto its shop's newly
        // seeded stages. not_started/shipped/invoiced map onto the matching
        // built-in role; anything mid-pipeline (cutting/fitting/welding/
        // finishing) collapses onto the shop's generic starter stage, since
        // those specific station names no longer exist as a fixed concept -
        // shops customize their own from here. A cancelled job's prior
        // position isn't preserved by the old data, so it's parked at Not
        // Started with cancelled_at set.
        DB::table('fabrication_jobs')->orderBy('id')->each(function ($job) {
            $stages = DB::table('job_stages')->where('shop_id', $job->shop_id)->get();

            $notStarted = $stages->firstWhere('role', 'not_started');
            $shipped = $stages->firstWhere('role', 'shipped');
            $invoiced = $stages->firstWhere('role', 'invoiced');
            $starter = $stages->first(fn ($stage) => $stage->role === null);

            $target = match ($job->status) {
                'shipped' => $shipped,
                'invoiced' => $invoiced,
                'not_started', 'cancelled' => $notStarted,
                default => $starter,
            };

            DB::table('fabrication_jobs')->where('id', $job->id)->update([
                'job_stage_id' => $target->id,
                'cancelled_at' => $job->status === 'cancelled' ? now() : null,
            ]);
        });

        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->foreignId('job_stage_id')->nullable(false)->change();
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->string('status')->default('not_started');
        });

        DB::table('fabrication_jobs')->orderBy('id')->each(function ($job) {
            $stage = DB::table('job_stages')->where('id', $job->job_stage_id)->first();

            $status = $job->cancelled_at !== null ? 'cancelled' : ($stage->role ?? 'cutting');

            DB::table('fabrication_jobs')->where('id', $job->id)->update(['status' => $status]);
        });

        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_stage_id');
            $table->dropColumn('cancelled_at');
        });
    }
};
