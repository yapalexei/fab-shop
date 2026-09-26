<?php

use App\Models\Plan;
use App\Models\Shop;

test('a shop on trial shares on_trial and trial_ends_at with the frontend', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('shop.on_trial', true)
            ->where('shop.trial_ends_at', $shop->trial_ends_at->toIso8601String()));
});

test('a shop past its trial shares on_trial as false', function () {
    $plan = Plan::factory()->create();
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('shop.on_trial', false));
});
