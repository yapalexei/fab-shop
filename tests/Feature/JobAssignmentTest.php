<?php

use App\Events\JobBoardUpdated;
use App\Models\Shop;
use Illuminate\Support\Facades\Event;

test('a job can be assigned to a shop member', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);
    $user = actingAsShopMember($shop);
    $assignee = actingAsShopMember($shop, 'Staff');

    $this->actingAs($user)->patch(route('jobs.assignee.update', $job), [
        'assigned_to' => $assignee->id,
    ])->assertRedirect();

    expect($job->refresh()->assigned_to)->toBe($assignee->id);

    $entry = $job->activitiesAsSubject()->latest()->first();
    expect($entry->description)->toBe("Assigned to {$assignee->name}")
        ->and($entry->causer_id)->toBe($user->id);
});

test('reassigning a job records who it moved from and to', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);
    $user = actingAsShopMember($shop);
    $first = actingAsShopMember($shop, 'Staff');
    $second = actingAsShopMember($shop, 'Staff');

    $job->forceFill(['assigned_to' => $first->id])->save();

    $this->actingAs($user)->patch(route('jobs.assignee.update', $job), [
        'assigned_to' => $second->id,
    ])->assertRedirect();

    $entry = $job->activitiesAsSubject()->latest()->first();
    expect($entry->description)->toBe("Reassigned from {$first->name} to {$second->name}");
});

test('a job can be unassigned', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);
    $user = actingAsShopMember($shop);
    $assignee = actingAsShopMember($shop, 'Staff');

    $job->forceFill(['assigned_to' => $assignee->id])->save();

    $this->actingAs($user)->patch(route('jobs.assignee.update', $job), [
        'assigned_to' => null,
    ])->assertRedirect();

    expect($job->refresh()->assigned_to)->toBeNull();

    $entry = $job->activitiesAsSubject()->latest()->first();
    expect($entry->description)->toBe("Unassigned from {$assignee->name}");
});

test('assigning a job broadcasts a job board update for the job\'s shop', function () {
    Event::fake([JobBoardUpdated::class]);

    $shop = Shop::factory()->create();
    $job = jobForShop($shop);
    $user = actingAsShopMember($shop);
    $assignee = actingAsShopMember($shop, 'Staff');

    $this->actingAs($user)->patch(route('jobs.assignee.update', $job), [
        'assigned_to' => $assignee->id,
    ])->assertRedirect();

    Event::assertDispatched(JobBoardUpdated::class, fn (JobBoardUpdated $event) => $event->shopId === $shop->id);
});

test('a job cannot be assigned to someone outside the shop', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $job = jobForShop($shop);
    $user = actingAsShopMember($shop);
    $outsider = actingAsShopMember($otherShop);

    $this->actingAs($user)->patch(route('jobs.assignee.update', $job), [
        'assigned_to' => $outsider->id,
    ])->assertSessionHasErrors('assigned_to');

    expect($job->refresh()->assigned_to)->toBeNull();
});

test('assigning needs jobs.update', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);
    $staff = actingAsShopMember($shop, 'Staff');
    $assignee = actingAsShopMember($shop, 'Staff');
    $shop->members()->updateExistingPivot($staff->id, ['permission_overrides' => ['jobs.update' => false]]);

    $this->actingAs($staff->fresh())->patch(route('jobs.assignee.update', $job), [
        'assigned_to' => $assignee->id,
    ])->assertForbidden();
});

test('a user cannot assign another shop\'s job', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $job = jobForShop($shopB);
    $user = actingAsShopMember($shopA);
    $assignee = actingAsShopMember($shopA, 'Staff');

    $this->actingAs($user)->patch(route('jobs.assignee.update', $job), [
        'assigned_to' => $assignee->id,
    ])->assertNotFound();
});
