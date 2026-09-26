<?php

namespace App\Http\Controllers;

use App\Events\JobBoardUpdated;
use App\Http\Requests\UpdateJobAssignmentRequest;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class JobAssignmentController extends Controller
{
    public function update(UpdateJobAssignmentRequest $request, Job $job): RedirectResponse
    {
        $assignedTo = $request->validated('assigned_to');

        // Read before the update, not through the relation after - a
        // cached belongsTo would still report the old user once assigned_to
        // has changed underneath it.
        $previousName = $job->assignedTo?->name;

        $job->update(['assigned_to' => $assignedTo]);

        $newName = $assignedTo ? User::find($assignedTo)?->name : null;

        $description = match (true) {
            $newName === null => "Unassigned from {$previousName}",
            $previousName === null => "Assigned to {$newName}",
            default => "Reassigned from {$previousName} to {$newName}",
        };

        activity()
            ->performedOn($job)
            ->causedBy(Auth::user())
            ->log($description);

        broadcast(new JobBoardUpdated($job->shop_id))->toOthers();

        return back()->with('status', $description.'.');
    }
}
