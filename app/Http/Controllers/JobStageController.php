<?php

namespace App\Http\Controllers;

use App\Enums\ShopResource;
use App\Http\Controllers\Concerns\AuthorizesShopResource;
use App\Http\Requests\ReorderJobStagesRequest;
use App\Http\Requests\StoreJobStageRequest;
use App\Http\Requests\UpdateJobStageRequest;
use App\Models\JobStage;
use App\Support\JobStagePositions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A shop's job board pipeline: Not Started, Shipped and Invoiced always
 * exist (see JobStageRole) and can't be renamed, reordered or deleted -
 * everything between them is the shop's own custom, ordered stages.
 */
class JobStageController extends Controller implements HasMiddleware
{
    use AuthorizesShopResource;

    protected static function shopResource(): ShopResource
    {
        return ShopResource::JobStages;
    }

    public function index(): Response
    {
        $stages = JobStage::query()->withCount('jobs')->orderBy('position')->get();

        return Inertia::render('JobStages/Index', [
            'stages' => $stages->map(fn (JobStage $stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'role' => $stage->role?->value,
                'is_built_in' => $stage->isBuiltIn(),
                'jobs_count' => $stage->jobs_count,
            ]),
        ]);
    }

    public function store(StoreJobStageRequest $request): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        $customStages = JobStage::query()->whereNull('role')->orderBy('position')->get();

        // Position is a placeholder - JobStagePositions::renumber() below
        // assigns the real one (appended at the end, just before Shipped).
        $stage = JobStage::create([...$request->validated(), 'position' => 0]);

        JobStagePositions::renumber($shop, $customStages->push($stage));

        return back()->with('status', "Added {$stage->name}.");
    }

    public function update(UpdateJobStageRequest $request, JobStage $jobStage): RedirectResponse
    {
        $jobStage->update($request->validated());

        return back()->with('status', "Renamed to {$jobStage->name}.");
    }

    public function destroy(JobStage $jobStage): RedirectResponse
    {
        if ($jobStage->isBuiltIn()) {
            return back()->with('error', "{$jobStage->name} is a fixed stage and can't be deleted.");
        }

        $jobsCount = $jobStage->jobs()->count();

        if ($jobsCount > 0) {
            return back()->with('error', "{$jobStage->name} still has {$jobsCount} job(s) in it - move them first.");
        }

        $shop = Auth::user()->currentShop;
        $name = $jobStage->name;
        $jobStage->delete();

        JobStagePositions::renumber($shop, JobStage::query()->whereNull('role')->orderBy('position')->get());

        return back()->with('status', "Deleted {$name}.");
    }

    /**
     * Reorders only the shop's custom stages - Not Started/Shipped/Invoiced
     * never move, so they're never part of the payload.
     */
    public function reorder(ReorderJobStagesRequest $request): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        $orderedIds = $request->validated('ordered_ids');

        $stages = JobStage::query()->whereNull('role')->whereIn('id', $orderedIds)->get()
            ->sortBy(fn (JobStage $stage) => array_search($stage->id, $orderedIds, true))
            ->values();

        JobStagePositions::renumber($shop, $stages);

        return back()->with('status', 'Stages reordered.');
    }
}
