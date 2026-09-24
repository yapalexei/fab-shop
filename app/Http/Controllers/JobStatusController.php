<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Events\JobBoardUpdated;
use App\Jobs\PushJobInvoiceToQuickBooks;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobStatusController extends Controller
{
    public function update(Request $request, Job $job): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(JobStatus::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = JobStatus::from($validated['status']);

        if (! $job->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move this job from {$job->status->label()} to {$target->label()}.",
            ]);
        }

        $from = $job->status;
        $job->update(['status' => $target]);

        $description = $target === JobStatus::Cancelled
            ? 'Job cancelled'.(filled($validated['reason'] ?? null) ? ": {$validated['reason']}" : '')." (was {$from->label()})"
            : "Status changed from {$from->label()} to {$target->label()}";

        activity()
            ->performedOn($job)
            ->causedBy(Auth::user())
            ->log($description);

        broadcast(new JobBoardUpdated($job->shop_id))->toOthers();

        if ($target === JobStatus::Invoiced) {
            PushJobInvoiceToQuickBooks::dispatch($job);
        }

        return back()->with('status', $description.'.');
    }
}
