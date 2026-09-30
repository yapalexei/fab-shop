<?php

use App\Enums\Feature;
use App\Models\Certification;
use App\Models\Job;
use App\Models\JobQualificationRecord;
use App\Models\Plan;
use App\Models\Procedure;
use App\Models\Shop;

/**
 * Creates a record directly (bypassing the HTTP endpoint) for test setup -
 * same reasoning as JobMaterialUsageTest's usageFor().
 */
function qualificationRecordFor(Job $job, array $attributes): JobQualificationRecord
{
    $record = new JobQualificationRecord;
    $record->forceFill(['shop_id' => $job->shop_id, 'job_id' => $job->id, ...$attributes]);
    $record->save();

    return $record;
}

test('logging a record for a certified performer snapshots was_qualified as true', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, 'in_progress');
    $procedure = Procedure::factory()->for($shop)->create();
    Certification::factory()->for($shop)->create(['user_id' => $user->id, 'procedure_id' => $procedure->id]);

    $this->actingAs($user)->post(route('jobs.qualification-records.store', $job), [
        'procedure_id' => $procedure->id, 'performed_by' => $user->id,
        'description' => 'Root pass, main frame joint', 'performed_at' => now()->toDateString(),
    ])->assertRedirect();

    $record = $job->qualificationRecords()->sole();
    expect($record->was_qualified)->toBeTrue()
        ->and($record->recorded_by)->toBe($user->id);
});

test('logging a record for someone with no matching certification snapshots was_qualified as false', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, 'in_progress');
    $procedure = Procedure::factory()->for($shop)->create();

    $this->actingAs($user)->post(route('jobs.qualification-records.store', $job), [
        'procedure_id' => $procedure->id, 'performed_by' => $user->id,
        'description' => 'Root pass', 'performed_at' => now()->toDateString(),
    ])->assertRedirect();

    expect($job->qualificationRecords()->sole()->was_qualified)->toBeFalse();
});

test('a certification that had already expired by the work date does not count as qualified', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, 'in_progress');
    $procedure = Procedure::factory()->for($shop)->create();
    Certification::factory()->for($shop)->create([
        'user_id' => $user->id, 'procedure_id' => $procedure->id,
        'issued_at' => now()->subYears(3), 'expires_at' => now()->subYear(),
    ]);

    $this->actingAs($user)->post(route('jobs.qualification-records.store', $job), [
        'procedure_id' => $procedure->id, 'performed_by' => $user->id,
        'description' => 'Root pass', 'performed_at' => now()->toDateString(),
    ]);

    expect($job->qualificationRecords()->sole()->was_qualified)->toBeFalse();
});

test('a certification still covers work performed before it later expires', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, 'in_progress');
    $procedure = Procedure::factory()->for($shop)->create();
    Certification::factory()->for($shop)->create([
        'user_id' => $user->id, 'procedure_id' => $procedure->id,
        'issued_at' => now()->subYear(), 'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($user)->post(route('jobs.qualification-records.store', $job), [
        'procedure_id' => $procedure->id, 'performed_by' => $user->id,
        'description' => 'Root pass', 'performed_at' => now()->subMonth()->toDateString(),
    ]);

    expect($job->qualificationRecords()->sole()->was_qualified)->toBeTrue();
});

test('was_qualified is left null when no procedure is selected', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, 'in_progress');

    $this->actingAs($user)->post(route('jobs.qualification-records.store', $job), [
        'performed_by' => $user->id, 'description' => 'General fit-up', 'performed_at' => now()->toDateString(),
    ]);

    expect($job->qualificationRecords()->sole()->was_qualified)->toBeNull();
});

test('logging and removing a record both write a matching activity entry', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, 'in_progress');

    $this->actingAs($user)->post(route('jobs.qualification-records.store', $job), [
        'performed_by' => $user->id, 'description' => 'Dye penetrant check', 'performed_at' => now()->toDateString(),
    ]);
    expect($job->activitiesAsSubject()->latest()->first()->description)->toContain('Dye penetrant check');

    $record = $job->qualificationRecords()->sole();
    $this->actingAs($user)->delete(route('jobs.qualification-records.destroy', [$job, $record]))
        ->assertRedirect();

    expect($job->qualificationRecords()->count())->toBe(0)
        ->and($job->activitiesAsSubject()->latest()->first()->description)->toContain('Dye penetrant check');
});

test('a record from a different job cannot be deleted through this job', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $jobA = jobForShop($shop, 'in_progress');
    $jobB = jobForShop($shop, 'in_progress');
    $record = qualificationRecordFor($jobB, [
        'performed_by' => $user->id, 'description' => 'x', 'performed_at' => now(), 'recorded_by' => $user->id,
    ]);

    $this->actingAs($user)->delete(route('jobs.qualification-records.destroy', [$jobA, $record]))
        ->assertNotFound();

    expect($jobB->qualificationRecords()->count())->toBe(1);
});

test('a user cannot log or see qualification records on another shop\'s job', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $foreignJob = jobForShop($otherShop, 'in_progress');

    $this->actingAs($user)->post(route('jobs.qualification-records.store', $foreignJob), [
        'performed_by' => $user->id, 'description' => 'x', 'performed_at' => now()->toDateString(),
    ])->assertNotFound();
});

test('someone without jobs.update cannot log or remove qualification records', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $shop->members()->updateExistingPivot($staff->id, ['permission_overrides' => ['jobs.update' => false]]);
    $job = jobForShop($shop, 'in_progress');
    $record = qualificationRecordFor($job, [
        'performed_by' => $staff->id, 'description' => 'x', 'performed_at' => now(), 'recorded_by' => $staff->id,
    ]);

    $this->actingAs($staff->fresh())->post(route('jobs.qualification-records.store', $job), [
        'performed_by' => $staff->id, 'description' => 'x', 'performed_at' => now()->toDateString(),
    ])->assertForbidden();
    $this->actingAs($staff->fresh())->delete(route('jobs.qualification-records.destroy', [$job, $record]))
        ->assertForbidden();
});

test('qualification records are read-only for a shop without the feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::JobBoard]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, 'in_progress');

    $this->actingAs($user)->post(route('jobs.qualification-records.store', $job), [
        'performed_by' => $user->id, 'description' => 'x', 'performed_at' => now()->toDateString(),
    ])->assertForbidden();

    // The job page itself still loads (job-board is included), just
    // without the qualification-records panel's data.
    $this->actingAs($user)->get(route('jobs.show', $job))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('qualificationRecords', null));
});

test('the job page includes qualification record data for a shop with the feature', function () {
    $shop = Shop::factory()->create(); // trial shop - has every feature
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, 'in_progress');
    $procedure = Procedure::factory()->for($shop)->create();
    qualificationRecordFor($job, [
        'procedure_id' => $procedure->id, 'performed_by' => $user->id, 'description' => 'Root pass',
        'performed_at' => now(), 'was_qualified' => true, 'recorded_by' => $user->id,
    ]);

    $this->actingAs($user)->get(route('jobs.show', $job))
        ->assertInertia(fn ($page) => $page
            ->has('qualificationRecords.records', 1)
            ->where('qualificationRecords.records.0.was_qualified', true)
            ->where('qualificationRecords.records.0.procedure', $procedure->name));
});
