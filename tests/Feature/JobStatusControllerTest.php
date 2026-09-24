<?php

use App\Enums\JobStatus;
use App\Events\JobBoardUpdated;
use App\Models\Shop;
use Illuminate\Support\Facades\Event;

test('a valid transition updates the status and logs it', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, JobStatus::NotStarted);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'status' => 'cutting',
    ])->assertRedirect();

    expect($job->refresh()->status)->toBe(JobStatus::Cutting);

    $entry = $job->activitiesAsSubject()->latest()->first();
    expect($entry->description)->toBe('Status changed from Not started to Cutting')
        ->and($entry->causer_id)->toBe($user->id);
});

test('a valid transition broadcasts a job board update for the job\'s shop', function () {
    Event::fake([JobBoardUpdated::class]);

    $shop = Shop::factory()->create();
    $job = jobForShop($shop, JobStatus::NotStarted);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'status' => 'cutting',
    ])->assertRedirect();

    Event::assertDispatched(JobBoardUpdated::class, fn (JobBoardUpdated $event) => $event->shopId === $shop->id);
});

test('an invalid transition is rejected', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, JobStatus::Finishing);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'status' => 'welding',
    ])->assertSessionHasErrors('status');

    expect($job->refresh()->status)->toBe(JobStatus::Finishing);
});

test('cancelling a job records the reason', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, JobStatus::Cutting);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'status' => 'cancelled',
        'reason' => 'Customer backed out',
    ])->assertRedirect();

    expect($job->refresh()->status)->toBe(JobStatus::Cancelled);

    $entry = $job->activitiesAsSubject()->latest()->first();
    expect($entry->description)->toBe('Job cancelled: Customer backed out (was Cutting)');
});

test('a user cannot change the status of another shop\'s job', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $job = jobForShop($shopB);
    $user = actingAsShopMember($shopA);

    $this->actingAs($user)->patch(route('jobs.status', $job), [
        'status' => 'cutting',
    ])->assertNotFound();
});
