<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Models\Job;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Jobs/Index', [
            'jobs' => Job::query()
                ->with('customer:id,name')
                ->latest()
                ->get()
                ->map(fn (Job $job) => $this->jobSummary($job)),
            'statuses' => collect(JobStatus::cases())->map(fn (JobStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    public function show(Job $job): Response
    {
        $job->load('quote.lineItems', 'customer', 'activitiesAsSubject.causer');

        return Inertia::render('Jobs/Show', [
            'job' => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'status' => $job->status->value,
                'status_label' => $job->status->label(),
                'available_transitions' => $this->mapTransitions($job),
                'total' => $job->total,
                'customer' => [
                    'name' => $job->customer->name,
                    'email' => $job->customer->email,
                ],
                'line_items' => $job->quote->lineItems->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'total' => $item->total,
                ]),
                'history' => $job->activitiesAsSubject->sortByDesc('created_at')->values()->map(fn ($activity) => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'causer' => $activity->causer?->name,
                    'created_at' => $activity->created_at->toIso8601String(),
                ]),
                'photos' => $job->photos()->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => $photo->getUrl(),
                    'thumb_url' => $photo->getUrl('thumb'),
                ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jobSummary(Job $job): array
    {
        return [
            'id' => $job->id,
            'job_number' => $job->job_number,
            'customer' => $job->customer->name,
            'status' => $job->status->value,
            'status_label' => $job->status->label(),
            'total' => $job->total,
            'available_transitions' => $this->mapTransitions($job),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapTransitions(Job $job): array
    {
        $forwardOrder = $job->status->order() + 1;

        return collect($job->availableTransitions())
            ->map(fn (JobStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'is_forward' => $status->order() === $forwardOrder,
            ])
            ->sortByDesc('is_forward')
            ->values()
            ->all();
    }
}
