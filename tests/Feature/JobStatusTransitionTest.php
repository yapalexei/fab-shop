<?php

use App\Enums\JobStageRole;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Shop;

/**
 * A representative shop pipeline - the fixed Not Started/Shipped/Invoiced
 * plus three custom stages in between, exercising the same shape the old
 * fixed 7-case JobStatus ladder did, but as shop-configured data.
 *
 * @return array<string, JobStage>
 */
function buildPipeline(): array
{
    $shop = Shop::factory()->create();

    // Shop::created already seeded Not Started/Setup/Shipped/Invoiced -
    // delete the default "Setup" and replace with a 3-stage custom pipeline
    // so these tests exercise skipping a *middle* stage, not just the one
    // default.
    JobStage::query()->where('shop_id', $shop->id)->whereNull('role')->delete();

    $notStarted = JobStage::query()->where('shop_id', $shop->id)->where('role', JobStageRole::NotStarted)->firstOrFail();
    $shipped = JobStage::query()->where('shop_id', $shop->id)->where('role', JobStageRole::Shipped)->firstOrFail();
    $invoiced = JobStage::query()->where('shop_id', $shop->id)->where('role', JobStageRole::Invoiced)->firstOrFail();

    $cutting = JobStage::factory()->for($shop)->create(['name' => 'Cutting', 'position' => 1]);
    $fitting = JobStage::factory()->for($shop)->create(['name' => 'Fitting', 'position' => 2]);
    $welding = JobStage::factory()->for($shop)->create(['name' => 'Welding', 'position' => 3]);

    $shipped->update(['position' => 4]);
    $invoiced->update(['position' => 5]);

    return compact('notStarted', 'cutting', 'fitting', 'welding', 'shipped', 'invoiced');
}

function jobAtStage(JobStage $stage): Job
{
    return Job::factory()->for($stage->shop)->make(['job_stage_id' => $stage->id])->setRelation('jobStage', $stage);
}

test('sequential forward is always allowed', function () {
    $s = buildPipeline();

    expect(jobAtStage($s['notStarted'])->canTransitionTo($s['cutting']))->toBeTrue()
        ->and(jobAtStage($s['cutting'])->canTransitionTo($s['fitting']))->toBeTrue()
        ->and(jobAtStage($s['fitting'])->canTransitionTo($s['welding']))->toBeTrue()
        ->and(jobAtStage($s['welding'])->canTransitionTo($s['shipped']))->toBeTrue()
        ->and(jobAtStage($s['shipped'])->canTransitionTo($s['invoiced']))->toBeTrue();
});

test('a job can skip ahead past stages it does not need, including all the way to invoiced', function () {
    $s = buildPipeline();

    expect(jobAtStage($s['notStarted'])->canTransitionTo($s['fitting']))->toBeTrue()
        ->and(jobAtStage($s['cutting'])->canTransitionTo($s['welding']))->toBeTrue()
        ->and(jobAtStage($s['welding'])->canTransitionTo($s['invoiced']))->toBeTrue();
});

test('a job still on the floor can be moved back to any earlier stage', function () {
    $s = buildPipeline();

    expect(jobAtStage($s['welding'])->canTransitionTo($s['notStarted']))->toBeTrue()
        ->and(jobAtStage($s['welding'])->canTransitionTo($s['cutting']))->toBeTrue()
        ->and(jobAtStage($s['fitting'])->canTransitionTo($s['cutting']))->toBeTrue();
});

test('shipped and invoiced cannot be walked back', function () {
    $s = buildPipeline();

    expect(jobAtStage($s['shipped'])->canTransitionTo($s['welding']))->toBeFalse()
        ->and(jobAtStage($s['invoiced'])->canTransitionTo($s['shipped']))->toBeFalse();
});

test('invoiced has no legal stage transitions at all', function () {
    $s = buildPipeline();
    $job = jobAtStage($s['invoiced']);

    foreach ($s as $stage) {
        expect($job->canTransitionTo($stage))->toBeFalse();
    }
});

test('a cancelled job has no legal transitions and cannot be cancelled again', function () {
    $s = buildPipeline();
    $job = jobAtStage($s['cutting']);
    $job->cancelled_at = now();

    foreach ($s as $stage) {
        expect($job->canTransitionTo($stage))->toBeFalse();
    }

    expect($job->canBeCancelled())->toBeFalse();
});

test('any non-cancelled, non-invoiced job can be cancelled', function () {
    $s = buildPipeline();

    foreach (['notStarted', 'cutting', 'fitting', 'welding', 'shipped', 'invoiced'] as $key) {
        expect(jobAtStage($s[$key])->canBeCancelled())->toBeTrue();
    }
});

test('availableTransitions only lists what canTransitionTo allows, in shop order', function () {
    $s = buildPipeline();

    $available = jobAtStage($s['fitting'])->availableTransitions();

    expect($available->pluck('id')->all())->toBe([
        $s['notStarted']->id,
        $s['cutting']->id,
        $s['welding']->id,
        $s['shipped']->id,
        $s['invoiced']->id,
    ]);
});
