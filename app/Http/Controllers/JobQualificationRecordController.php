<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobQualificationRecordRequest;
use App\Models\Certification;
use App\Models\Job;
use App\Models\JobQualificationRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class JobQualificationRecordController extends Controller
{
    public function store(StoreJobQualificationRecordRequest $request, Job $job): RedirectResponse
    {
        $data = $request->validated();

        $record = $job->qualificationRecords()->create([
            ...$data,
            'was_qualified' => $this->wasQualified($data),
            'recorded_by' => Auth::id(),
        ]);

        activity()
            ->performedOn($job)
            ->causedBy(Auth::user())
            ->log(sprintf(
                'Logged qualification record: %s%s',
                $record->description,
                $record->was_qualified === false ? ' (not certified for this procedure)' : '',
            ));

        return back()->with('status', 'Qualification record logged.');
    }

    public function destroy(Job $job, JobQualificationRecord $qualificationRecord): RedirectResponse
    {
        abort_unless($qualificationRecord->job_id === $job->id, 404);

        activity()
            ->performedOn($job)
            ->causedBy(Auth::user())
            ->log("Removed logged qualification record: {$qualificationRecord->description}");

        $qualificationRecord->delete();

        return back()->with('status', 'Qualification record removed.');
    }

    /**
     * Null when there's no procedure to check against - true/false
     * otherwise, snapshotting whether performed_by held a certification for
     * procedure_id that covered performed_at. Computed once here and never
     * recomputed, so a cert expiring later doesn't retroactively flag work
     * that was compliant when it happened.
     *
     * @param  array<string, mixed>  $data
     */
    private function wasQualified(array $data): ?bool
    {
        // procedure_id is nullable and may be entirely absent from
        // validated() when the request omits it, not just null.
        if (($data['procedure_id'] ?? null) === null) {
            return null;
        }

        return Certification::query()
            ->where('user_id', $data['performed_by'])
            ->where('procedure_id', $data['procedure_id'])
            ->get()
            ->contains(fn (Certification $certification) => $certification->coversOn($data['performed_at']));
    }
}
