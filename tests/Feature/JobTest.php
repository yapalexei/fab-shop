<?php

use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Quote;
use App\Models\Shop;

/**
 * Job::factory()->for($shop) only overrides shop_id - its definition()
 * independently creates a Quote+Customer under their own Shop, so the
 * customer/quote relations would resolve to nothing once the tenant scope
 * kicks in unless we build them under the same shop explicitly. Shared
 * across the Job-related test files.
 */
function jobForShop(Shop $shop, JobStatus $status = JobStatus::NotStarted): Job
{
    $customer = Customer::factory()->for($shop)->create();
    $quote = Quote::factory()->for($shop)->create(['customer_id' => $customer->id]);

    return Job::factory()->for($shop)->create([
        'quote_id' => $quote->id,
        'customer_id' => $customer->id,
        'status' => $status,
    ]);
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
