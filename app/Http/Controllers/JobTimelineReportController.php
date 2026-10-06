<?php

namespace App\Http\Controllers;

use App\Enums\JobStageRole;
use App\Models\Job;
use App\Models\JobStage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class JobTimelineReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'range' => ['nullable', 'in:week,month'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'stages' => ['nullable', 'array'],
            'stages.*' => ['integer'],
            'overdue' => ['nullable', 'boolean'],
        ]);

        $shop = Auth::user()->currentShop;
        $tz = $shop->localTimezone();
        $range = $validated['range'] ?? 'month';
        $anchor = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], $tz)
            : $shop->localNow();

        // Weeks start on Monday; a month is the anchor's calendar month.
        $start = $range === 'week' ? $anchor->startOfWeek() : $anchor->startOfMonth();
        $end = $range === 'week' ? $anchor->endOfWeek() : $anchor->endOfMonth();

        // "Include" filter: only jobs currently sitting in the chosen stages.
        // Not Started is never offered - those jobs have no period to show.
        $stages = JobStage::query()
            ->where(fn ($q) => $q->whereNull('role')->orWhere('role', '!=', JobStageRole::NotStarted))
            ->orderBy('position')
            ->get();
        $includedStageIds = collect($validated['stages'] ?? [])->map(fn ($id) => (int) $id)
            ->intersect($stages->pluck('id'))
            ->values();

        $overdueOnly = $request->boolean('overdue');

        // Overlap test in UTC: started before the range ends, and either
        // still in progress or finished after the range began.
        $jobs = Job::query()
            ->with('customer:id,name', 'jobStage', 'assignedTo:id,name')
            ->whereNotNull('started_at')
            ->whereNull('cancelled_at')
            ->where('started_at', '<=', $end->utc())
            ->where(fn ($q) => $q->whereNull('finished_at')->orWhere('finished_at', '>=', $start->utc()))
            ->when($overdueOnly, fn ($q) => $q->whereNull('finished_at')->whereDate('due_date', '<', $shop->localNow()->toDateString()))
            ->when($includedStageIds->isNotEmpty(), fn ($q) => $q->whereIn('job_stage_id', $includedStageIds))
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        return Inertia::render('Reports/JobTimeline', [
            'filters' => [
                'range' => $range,
                'date' => $anchor->toDateString(),
                'stages' => $includedStageIds->map(fn ($id) => (string) $id)->all(),
                'overdue' => $overdueOnly,
            ],
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'today' => $shop->localNow()->toDateString(),
            'stages' => $stages->map(fn (JobStage $stage) => [
                'value' => (string) $stage->id,
                'label' => $stage->name,
            ]),
            'jobs' => $jobs->map(fn (Job $job) => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'customer' => $job->customer->name,
                'stage_label' => $job->jobStage->name,
                'assignee' => $job->assignedTo?->name,
                'started_on' => $job->started_at->copy()->setTimezone($tz)->toDateString(),
                'ended_on' => $job->finished_at?->copy()->setTimezone($tz)->toDateString(),
                'due_date' => $job->due_date?->toDateString(),
            ]),
        ]);
    }
}
