<?php

namespace App\Support;

use App\Enums\JobStageRole;
use App\Models\JobStage;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one place job_stages.position gets assigned, so create/reorder/delete
 * can't drift out of sync with each other. Not Started always sits at
 * position 0 and Shipped/Invoiced always sit at the top two positions;
 * everything else is the shop's own custom stages, renumbered 1..N in
 * whatever order is passed in.
 */
final class JobStagePositions
{
    /**
     * @param  Collection<int, JobStage>  $orderedCustomStages
     */
    public static function renumber(Shop $shop, Collection $orderedCustomStages): void
    {
        DB::transaction(function () use ($shop, $orderedCustomStages) {
            $builtIns = JobStage::query()
                ->where('shop_id', $shop->id)
                ->whereNotNull('role')
                ->get()
                ->keyBy(fn (JobStage $stage) => $stage->role->value);

            $builtIns[JobStageRole::NotStarted->value]->update(['position' => 0]);

            $orderedCustomStages->values()->each(
                fn (JobStage $stage, int $index) => $stage->update(['position' => $index + 1]),
            );

            $builtIns[JobStageRole::Shipped->value]->update(['position' => $orderedCustomStages->count() + 1]);
            $builtIns[JobStageRole::Invoiced->value]->update(['position' => $orderedCustomStages->count() + 2]);
        });
    }
}
