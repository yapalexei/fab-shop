<?php

use App\Models\Shop;

// jobForShop() / resolveJobStage() live in tests/Pest.php so every test file
// can use them whatever order (or process) the files run in.

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
