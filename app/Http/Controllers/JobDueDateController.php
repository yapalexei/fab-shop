<?php

namespace App\Http\Controllers;

use App\Events\JobBoardUpdated;
use App\Http\Requests\UpdateJobDueDateRequest;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class JobDueDateController extends Controller
{
    public function update(UpdateJobDueDateRequest $request, Job $job): RedirectResponse
    {
        $dueDate = $request->validated('due_date');
        $previous = $job->due_date?->format('M j, Y');

        $job->update(['due_date' => $dueDate]);

        $new = $job->due_date?->format('M j, Y');

        $description = match (true) {
            $new === null => 'Due date cleared (was '.$previous.')',
            $previous === null => "Due date set to {$new}",
            default => "Due date changed from {$previous} to {$new}",
        };

        activity()
            ->performedOn($job)
            ->causedBy(Auth::user())
            ->log($description);

        broadcast(new JobBoardUpdated($job->shop_id))->toOthers();

        return back()->with('status', $description.'.');
    }
}
