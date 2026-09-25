<?php

use App\Events\JobBoardUpdated;
use App\Models\Shop;
use Illuminate\Support\Facades\Event;

test('a valid transition updates the stage and logs it', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, 'not_started');
    $setup = resolveJobStage($shop, 'in_progress');
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'job_stage_id' => $setup->id,
    ])->assertRedirect();

    expect($job->refresh()->job_stage_id)->toBe($setup->id);

    $entry = $job->activitiesAsSubject()->latest()->first();
    expect($entry->description)->toBe('Status changed from Not Started to Setup')
        ->and($entry->causer_id)->toBe($user->id);
});

test('a valid transition broadcasts a job board update for the job\'s shop', function () {
    Event::fake([JobBoardUpdated::class]);

    $shop = Shop::factory()->create();
    $job = jobForShop($shop, 'not_started');
    $setup = resolveJobStage($shop, 'in_progress');
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'job_stage_id' => $setup->id,
    ])->assertRedirect();

    Event::assertDispatched(JobBoardUpdated::class, fn (JobBoardUpdated $event) => $event->shopId === $shop->id);
});

test('an invalid transition is rejected', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, 'shipped');
    $notStarted = resolveJobStage($shop, 'not_started');
    $user = actingAsShopMember($shop);

    // Once shipped, no backward moves are legal any more.
    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'job_stage_id' => $notStarted->id,
    ])->assertSessionHasErrors('job_stage_id');

    expect($job->refresh()->job_stage_id)->toBe(resolveJobStage($shop, 'shipped')->id);
});

test('a job can skip a stage it does not need', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, 'not_started');
    $shipped = resolveJobStage($shop, 'shipped');
    $user = actingAsShopMember($shop);

    // Not Started straight to Shipped - skipping the shop's custom Setup
    // stage entirely is allowed on purpose.
    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'job_stage_id' => $shipped->id,
    ])->assertRedirect();

    expect($job->refresh()->job_stage_id)->toBe($shipped->id);
});

test('cancelling a job records the reason', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, 'in_progress');
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'cancel' => true,
        'reason' => 'Customer backed out',
    ])->assertRedirect();

    expect($job->refresh()->cancelled_at)->not->toBeNull();

    $entry = $job->activitiesAsSubject()->latest()->first();
    expect($entry->description)->toBe('Job cancelled: Customer backed out (was Setup)');
});

test('a cancelled job cannot be cancelled again', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, 'in_progress');
    // Not mass-assignable by design (same pattern as suspended_at/
    // onboarded_at elsewhere) - only JobStatusController::cancel() sets it.
    $job->forceFill(['cancelled_at' => now()])->save();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'cancel' => true,
    ])->assertSessionHasErrors('cancel');
});

test('a user cannot change the status of another shop\'s job', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $job = jobForShop($shopB);
    $setup = resolveJobStage($shopB, 'in_progress');
    $user = actingAsShopMember($shopA);

    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'job_stage_id' => $setup->id,
    ])->assertNotFound();
});
