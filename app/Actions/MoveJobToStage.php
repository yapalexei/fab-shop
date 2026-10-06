<?php

namespace App\Actions;

use App\Enums\Feature;
use App\Enums\JobStageRole;
use App\Events\JobBoardUpdated;
use App\Jobs\PushJobInvoiceToQuickBooks;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\User;

/**
 * Moves a job to another stage and does everything that goes with it: its
 * start/finish dates, the invoiced timestamp, the History entry, the live
 * board update and the QuickBooks push. The job board and the MCP server
 * both call this, so a move means the same thing whoever makes it.
 *
 * It does not decide whether the move is *allowed* - callers check
 * Job::canTransitionTo() (and their own permissions) first.
 */
class MoveJobToStage
{
    /**
     * @return string The description written to the job's History.
     */
    public function __invoke(Job $job, JobStage $target, ?User $by = null): string
    {
        $from = $job->jobStage;
        $job->job_stage_id = $target->id;

        $this->recordPeriod($job, $target);

        if ($target->role === JobStageRole::Invoiced) {
            $job->invoiced_at = now();
        }

        $job->save();

        $description = "Status changed from {$from->name} to {$target->name}";

        activity()
            ->performedOn($job)
            ->causedBy($by)
            ->log($description);

        broadcast(new JobBoardUpdated($job->shop_id))->toOthers();

        if ($target->role === JobStageRole::Invoiced && Feature::QuickbooksSync->isShipped()) {
            PushJobInvoiceToQuickBooks::dispatch($job);
        }

        return $description;
    }

    /**
     * Maintains the job's period for the calendar/timeline: started_at is set
     * the first time it leaves Not Started (and cleared if it's moved back
     * there), finished_at the first time it reaches Shipped. Keyed off stage
     * roles, never names. Skipping straight to Shipped/Invoiced starts and
     * finishes the job in the same moment.
     */
    private function recordPeriod(Job $job, JobStage $target): void
    {
        if ($target->role === JobStageRole::NotStarted) {
            $job->started_at = null;

            return;
        }

        $job->started_at ??= now();

        if (in_array($target->role, [JobStageRole::Shipped, JobStageRole::Invoiced], true)) {
            $job->finished_at ??= now();
        }
    }
}
