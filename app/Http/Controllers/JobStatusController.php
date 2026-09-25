<?php

namespace App\Http\Controllers;

use App\Enums\JobStageRole;
use App\Events\JobBoardUpdated;
use App\Jobs\PushJobInvoiceToQuickBooks;
use App\Models\Job;
use App\Models\JobStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class JobStatusController extends Controller
{
    public function update(Request $request, Job $job): RedirectResponse
    {
        $validated = $request->validate([
            // Either a move (job_stage_id) or a cancellation - never both.
            'job_stage_id' => ['required_without:cancel', 'nullable', 'integer', 'exists:job_stages,id'],
            'cancel' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return ($validated['cancel'] ?? false)
            ? $this->cancel($job, $validated['reason'] ?? null)
            : $this->move($job, (int) $validated['job_stage_id']);
    }

    private function move(Job $job, int $targetStageId): RedirectResponse
    {
        abort_unless(Gate::allows('jobs.update'), 403);

        $target = JobStage::query()->where('shop_id', $job->shop_id)->findOrFail($targetStageId);

        if (! $job->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'job_stage_id' => "Cannot move this job from {$job->jobStage->name} to {$target->name}.",
            ]);
        }

        $from = $job->jobStage;
        $job->job_stage_id = $target->id;

        if ($target->role === JobStageRole::Invoiced) {
            $job->invoiced_at = now();
        }

        $job->save();

        $description = "Status changed from {$from->name} to {$target->name}";

        $this->logAndBroadcast($job, $description);

        if ($target->role === JobStageRole::Invoiced) {
            PushJobInvoiceToQuickBooks::dispatch($job);
        }

        return back()->with('status', $description.'.');
    }

    private function cancel(Job $job, ?string $reason): RedirectResponse
    {
        abort_unless(Gate::allows('jobs.cancel'), 403);

        if (! $job->canBeCancelled()) {
            throw ValidationException::withMessages([
                'cancel' => 'This job is already cancelled.',
            ]);
        }

        $wasAt = $job->jobStage->name;
        $job->cancelled_at = now();
        $job->save();

        $description = 'Job cancelled'.(filled($reason) ? ": {$reason}" : '')." (was {$wasAt})";

        $this->logAndBroadcast($job, $description);

        return back()->with('status', $description.'.');
    }

    private function logAndBroadcast(Job $job, string $description): void
    {
        activity()
            ->performedOn($job)
            ->causedBy(Auth::user())
            ->log($description);

        broadcast(new JobBoardUpdated($job->shop_id))->toOthers();
    }
}
