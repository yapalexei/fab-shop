<?php

use App\Enums\JobStageRole;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Quote;
use App\Models\Shop;

/**
 * Job::factory()->for($shop) only overrides shop_id - its definition()
 * independently creates a Quote+Customer (and, via JobStage::whereNull, a
 * job_stage_id) under their own Shop, so those relations would resolve to
 * nothing (or the wrong shop's stage) once the tenant scope kicks in unless
 * we build them under the same shop explicitly. Shared across the
 * Job-related test files.
 *
 * $stageKey is one of 'not_started' (default), 'shipped', 'invoiced', or
 * omitted/anything else for the shop's generic custom stage ("Setup") -
 * standing in for "some mid-pipeline stage" the way JobStatus::Cutting used
 * to before stages became shop-configurable.
 */
function jobForShop(Shop $shop, ?string $stageKey = null): Job
{
    $customer = Customer::factory()->for($shop)->create();
    $quote = Quote::factory()->for($shop)->create(['customer_id' => $customer->id]);

    return Job::factory()->for($shop)->create([
        'quote_id' => $quote->id,
        'customer_id' => $customer->id,
        'job_stage_id' => resolveJobStage($shop, $stageKey)->id,
    ]);
}

function resolveJobStage(Shop $shop, ?string $stageKey = null): JobStage
{
    $query = JobStage::query()->where('shop_id', $shop->id);

    return match ($stageKey) {
        'shipped' => (clone $query)->where('role', JobStageRole::Shipped)->firstOrFail(),
        'invoiced' => (clone $query)->where('role', JobStageRole::Invoiced)->firstOrFail(),
        'not_started', null => (clone $query)->where('role', JobStageRole::NotStarted)->firstOrFail(),
        default => (clone $query)->whereNull('role')->firstOrFail(),
    };
}

test('a shop member can view their own jobs', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('jobs.index'))
        ->assertInertia(fn ($page) => $page->component('Jobs/Index')->has('jobs', 1));

    $this->actingAs($user)->get(route('jobs.show', $job))
        ->assertInertia(fn ($page) => $page->component('Jobs/Show')
            ->where('job.id', $job->id));
});

test('a user cannot view another shop\'s job', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $job = jobForShop($shopB);
    $user = actingAsShopMember($shopA);

    $this->actingAs($user)->get(route('jobs.show', $job))->assertNotFound();
});
