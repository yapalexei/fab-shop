<?php

namespace App\Http\Controllers;

use App\Enums\JobStageRole;
use App\Models\Job;
use App\Models\JobStage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class JobTimelineReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // The page re-requests itself on every click with its current
        // filters, so "overdue" arrives as the query-string text "true" or
        // "false" - which Laravel's `boolean` rule rejects (it only takes
        // true/false/1/0). Normalise it first, or every button on the page
        // fails validation and just reloads the page unchanged.
        if ($request->has('overdue')) {
            $request->merge(['overdue' => $request->boolean('overdue')]);
        }

        $validated = $request->validate([
            'range' => ['nullable', 'in:week,month,year'],
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

        // Weeks start on Monday; a month or a year is the anchor's calendar
        // month or year.
        [$start, $end] = match ($range) {
            'week' => [$anchor->startOfWeek(), $anchor->endOfWeek()],
            'year' => [$anchor->startOfYear(), $anchor->endOfYear()],
            default => [$anchor->startOfMonth(), $anchor->endOfMonth()],
        };

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

        // Money is gated on the server, not just hidden in the UI: without
        // the permission the props below don't exist at all (Inertia props
        // are readable in the page source).
        $canSeeFinancials = Gate::allows('financials.view');

        // Overlap test in UTC: started before the range ends, and either
        // still in progress or finished after the range began.
        $jobs = Job::query()
            ->with('customer:id,name', 'jobStage', 'assignedTo:id,name')
            ->when($canSeeFinancials, fn ($q) => $q->with('quote.lineItems'))
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
            // Totals over the jobs shown (after the filters). Each job counts
            // in full, even if only part of it falls inside the period.
            'financials' => $canSeeFinancials ? $this->totals($jobs) : null,
            'jobs' => $jobs->map(fn (Job $job) => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'customer' => $job->customer->name,
                'stage_label' => $job->jobStage->name,
                'assignee' => $job->assignedTo?->name,
                'started_on' => $job->started_at->copy()->setTimezone($tz)->toDateString(),
                'ended_on' => $job->finished_at?->copy()->setTimezone($tz)->toDateString(),
                'due_date' => $job->due_date?->toDateString(),
                ...($canSeeFinancials ? $this->money($job) : []),
            ]),
        ]);
    }

    /**
     * What a job is worth and what it should earn, from its quote: the
     * price it was won at, the shop's cost before markup, and the
     * difference. Estimated - it is the quote's margin, not actual spend.
     *
     * @return array{amount: float, profit: float}
     */
    private function money(Job $job): array
    {
        return [
            'amount' => round($job->quote->total, 2),
            'profit' => round($job->quote->total - $job->quote->cost, 2),
        ];
    }

    /**
     * @param  Collection<int, Job>  $jobs
     * @return array{jobs: int, amount: float, cost: float, profit: float, margin_percent: float|null}
     */
    private function totals(Collection $jobs): array
    {
        $amount = $jobs->sum(fn (Job $job) => $job->quote->total);
        $cost = $jobs->sum(fn (Job $job) => $job->quote->cost);

        return [
            'jobs' => $jobs->count(),
            'amount' => round($amount, 2),
            'cost' => round($cost, 2),
            'profit' => round($amount - $cost, 2),
            'margin_percent' => $amount > 0 ? round(($amount - $cost) / $amount * 100, 1) : null,
        ];
    }
}
