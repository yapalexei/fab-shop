<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobMaterialUsageRequest;
use App\Models\Job;
use App\Models\JobMaterialUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class JobMaterialUsageController extends Controller
{
    public function store(StoreJobMaterialUsageRequest $request, Job $job): RedirectResponse
    {
        $usage = $job->materialUsages()->create([
            ...$request->validated(),
            'recorded_by' => Auth::id(),
        ]);

        activity()
            ->performedOn($job)
            ->causedBy(Auth::user())
            ->log(sprintf(
                'Logged %s %s of %s (%s, %s)',
                rtrim(rtrim((string) $usage->quantity, '0'), '.'),
                $usage->unit ?: 'unit(s)',
                $usage->description,
                $usage->type->label(),
                Number::currency((float) $usage->unit_cost),
            ));

        return back()->with('status', 'Material usage logged.');
    }

    public function destroy(Job $job, JobMaterialUsage $materialUsage): RedirectResponse
    {
        abort_unless($materialUsage->job_id === $job->id, 404);

        activity()
            ->performedOn($job)
            ->causedBy(Auth::user())
            ->log("Removed logged material: {$materialUsage->description}");

        $materialUsage->delete();

        return back()->with('status', 'Material usage entry removed.');
    }
}
