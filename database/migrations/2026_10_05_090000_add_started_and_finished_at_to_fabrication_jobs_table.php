<?php

use App\Models\Job;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The calendar and timeline report need a job's period: started_at is
     * when it first left the Not Started stage, finished_at when it reached
     * the Shipped stage (work done). Neither was recorded before, so
     * existing jobs are backfilled from their status-change activity log,
     * the closest existing record - falling back to invoiced_at/updated_at
     * where the log has no matching entry.
     */
    public function up(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('assigned_to');
            $table->timestamp('finished_at')->nullable()->after('started_at');
        });

        $subjectType = Job::class;

        $roles = DB::table('job_stages')->pluck('role', 'id');

        DB::table('fabrication_jobs')->orderBy('id')->each(function ($job) use ($roles, $subjectType) {
            $role = $roles[$job->job_stage_id] ?? null;

            if ($role === 'not_started') {
                return;
            }

            $moves = DB::table('activity_log')
                ->where('subject_type', $subjectType)
                ->where('subject_id', $job->id)
                ->where('description', 'like', 'Status changed from %')
                ->orderBy('created_at');

            $startedAt = (clone $moves)->value('created_at') ?? $job->updated_at;
            $finishedAt = null;

            if (in_array($role, ['shipped', 'invoiced'], true)) {
                $finishedAt = (clone $moves)->where('description', 'like', '% to Shipped')->value('created_at')
                    ?? $job->invoiced_at
                    ?? $job->updated_at;
            }

            DB::table('fabrication_jobs')->where('id', $job->id)->update([
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('fabrication_jobs', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'finished_at']);
        });
    }
};
