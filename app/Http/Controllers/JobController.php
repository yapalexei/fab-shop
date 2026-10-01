<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\Job;
use App\Models\JobMaterialUsage;
use App\Models\JobQualificationRecord;
use App\Models\JobStage;
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
                ->with('customer:id,name', 'jobStage', 'assignedTo:id,name')
                // id as a tiebreaker, not just created_at: several jobs can
                // share the same timestamp (bulk-seeded, or converted in the
                // same request), and an unstable order between requests
                // reshuffles cards on every board refresh - visibly janky
                // now that moves animate, since unrelated cards would
                // "jiggle" alongside the one that actually moved.
                ->latest()
                ->orderByDesc('id')
                ->get()
                ->map(fn (Job $job) => $this->jobSummary($job)),
            // Drag-and-drop is a shop setting (off by default); moving still
            // needs jobs.update, and every drop is confirmed before it's sent.
            'dragEnabled' => Auth::user()->currentShop->jobBoardDragEnabled() && Gate::allows('jobs.update'),
            'stages' => JobStage::query()->orderBy('position')->get()->map(fn (JobStage $stage) => [
                'value' => (string) $stage->id,
                'label' => $stage->name,
                'role' => $stage->role?->value,
            ]),
            // For the assignee picker - same {id, name} shape qualification
            // records already sends for its own member dropdown.
            'members' => Auth::user()->currentShop->members()->orderBy('name')->get(['users.id', 'users.name']),
            'canAssign' => Gate::allows('jobs.update'),
        ]);
    }

    public function show(Job $job): Response
    {
        $job->load('quote.lineItems', 'customer', 'jobStage', 'activitiesAsSubject.causer');

        $shop = Auth::user()->currentShop;
        // Entitled shops get the panel; a shop that lost the feature (trial
        // ended) still sees its existing entries, read-only, but nothing
        // when it never logged any - "don't send what they're not entitled to".
        $materialTrackingEnabled = $shop->hasFeature(Feature::MaterialTracking);
        $qualificationRecordsEnabled = $shop->hasFeature(Feature::QualificationRecords);
        $hasMaterialTracking = $materialTrackingEnabled
            || ($shop->canViewFeature(Feature::MaterialTracking) && $job->materialUsages()->exists());
        $hasQualificationRecords = $qualificationRecordsEnabled
            || ($shop->canViewFeature(Feature::QualificationRecords) && $job->qualificationRecords()->exists());

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
                'stage' => (string) $job->job_stage_id,
                'stage_label' => $job->jobStage->name,
                'cancelled' => $job->isCancelled(),
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
            'materialTracking' => $hasMaterialTracking ? [...$this->materialTrackingProps($job), 'read_only' => ! $materialTrackingEnabled] : null,
            'qualificationRecords' => $hasQualificationRecords ? [...$this->qualificationRecordProps($job), 'read_only' => ! $qualificationRecordsEnabled] : null,
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
                    // Only an unsized price is unambiguous - the cheapest of several
                    // sizes (4 in vs 4 ft) would be a misleading starting point.
                    'suggested_cost' => $material->businessPartners->whereNull('pivot.dimension')->min('pivot.price'),
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
            'stage' => (string) $job->job_stage_id,
            'stage_label' => $job->jobStage->name,
            'cancelled' => $job->isCancelled(),
            'total' => $job->total,
            'available_transitions' => $this->mapTransitions($job),
            'assigned_to' => $job->assigned_to,
            'assignee' => $job->assignedTo?->name,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapTransitions(Job $job): array
    {
        // Only offer what this user may actually do - the server enforces
        // the same split in JobStatusController.
        $canMove = Gate::allows('jobs.update');
        $canCancel = Gate::allows('jobs.cancel');

        $moves = $canMove
            ? $job->availableTransitions()->map(fn (JobStage $stage) => [
                'value' => (string) $stage->id,
                'label' => $stage->name,
                'is_forward' => $stage->position > $job->jobStage->position,
                'role' => $stage->role?->value,
            ])
            : collect();

        // A synthetic entry, not a JobStage row - cancellation is a side
        // flag now (see Job::cancelled_at), not a rung on the ladder.
        $cancel = $canCancel && $job->canBeCancelled()
            ? collect([['value' => 'cancelled', 'label' => 'Cancel', 'is_forward' => false, 'role' => null]])
            : collect();

        return $moves->concat($cancel)->sortByDesc('is_forward')->values()->all();
    }
}
