<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobMaterialUsage;
use App\Models\JobQualificationRecord;
use App\Models\Material;
use App\Models\Procedure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
            // Drag-and-drop is a shop setting (off by default); moving still
            // needs jobs.update, and every drop is confirmed before it's sent.
            'dragEnabled' => Auth::user()->currentShop->jobBoardDragEnabled() && Gate::allows('jobs.update'),
            'statuses' => collect(JobStatus::cases())->map(fn (JobStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    public function show(Job $job): Response
    {
        $job->load('quote.lineItems', 'customer', 'activitiesAsSubject.causer');

        $shop = Auth::user()->currentShop;
        $hasMaterialTracking = $shop->hasFeature(Feature::MaterialTracking);
        $hasQualificationRecords = $shop->hasFeature(Feature::QualificationRecords);

        if ($hasMaterialTracking) {
            $job->load('materialUsages.material', 'materialUsages.recordedBy');
        }

        if ($hasQualificationRecords) {
            $job->load('qualificationRecords.procedure', 'qualificationRecords.performedBy', 'qualificationRecords.recordedBy');
        }

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
            // Omitted entirely (not just hidden) for a shop without the
            // feature - same "don't send what they're not entitled to" rule
            // the dashboard's financials prop follows.
            'materialTracking' => $hasMaterialTracking ? $this->materialTrackingProps($job) : null,
            'qualificationRecords' => $hasQualificationRecords ? $this->qualificationRecordProps($job) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function materialTrackingProps(Job $job): array
    {
        return [
            'usages' => $job->materialUsages->sortByDesc('created_at')->values()->map(fn (JobMaterialUsage $usage) => [
                'id' => $usage->id,
                'material_id' => $usage->material_id,
                'description' => $usage->description,
                'unit' => $usage->unit,
                'quantity' => (float) $usage->quantity,
                'unit_cost' => (float) $usage->unit_cost,
                'cost' => $usage->cost,
                'type' => $usage->type->value,
                'notes' => $usage->notes,
                'recorded_by' => $usage->recordedBy?->name,
                'created_at' => $usage->created_at->toIso8601String(),
            ]),
            'cost_summary' => [
                'estimated' => $job->estimated_material_cost,
                'actual' => $job->actual_material_cost,
                'scrap' => $job->scrap_cost,
                'variance' => $job->material_cost_variance,
            ],
            'materials' => Material::query()->with('businessPartners')->orderBy('name')->get()
                ->map(fn (Material $material) => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'unit' => $material->unit,
                    // The cheapest known supplier price, as a starting point
                    // for unit cost - always overridable, since the whole
                    // point of tracking actual cost is that it can differ.
                    'suggested_cost' => $material->businessPartners->min('pivot.price'),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function qualificationRecordProps(Job $job): array
    {
        $shop = Auth::user()->currentShop;

        return [
            'records' => $job->qualificationRecords->sortByDesc('created_at')->values()->map(fn (JobQualificationRecord $record) => [
                'id' => $record->id,
                'procedure_id' => $record->procedure_id,
                'procedure' => $record->procedure?->name,
                'performed_by' => $record->performedBy?->name,
                'description' => $record->description,
                'performed_at' => $record->performed_at->toDateString(),
                'was_qualified' => $record->was_qualified,
                'notes' => $record->notes,
                'recorded_by' => $record->recordedBy?->name,
                'created_at' => $record->created_at->toIso8601String(),
            ]),
            'procedures' => Procedure::query()->orderBy('name')->get(['id', 'name']),
            'members' => $shop->members()->orderBy('name')->get(['users.id', 'users.name']),
        ];
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

        // Only offer what this user may actually do - the server enforces
        // the same split in JobStatusController.
        $canMove = Gate::allows('jobs.update');
        $canCancel = Gate::allows('jobs.cancel');

        return collect($job->availableTransitions())
            ->filter(fn (JobStatus $status) => $status === JobStatus::Cancelled ? $canCancel : $canMove)
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
