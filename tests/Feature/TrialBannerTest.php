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

test('the trial-ended notice shows for a shop that never subscribed', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => null]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('shop.show_trial_ended_notice', true));
});

test('the trial-ended notice never shows once the shop has subscribed', function () {
    $paid = Plan::factory()->create(['monthly_price_cents' => 4900]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $paid->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('shop.show_trial_ended_notice', false));

    $viaRenewal = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => null, 'subscription_renews_at' => now()->addMonth()]);
    $this->actingAs(actingAsShopMember($viaRenewal))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('shop.show_trial_ended_notice', false));
});

test('dismissing the trial-ended notice is saved per member and shop', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => null]);
    $user = actingAsShopMember($shop);
    $other = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('trial-ended-notice.dismiss'))->assertRedirect();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('shop.show_trial_ended_notice', false));
    $this->actingAs($other)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('shop.show_trial_ended_notice', true));

    // A trial that is extended and then ends again brings the notice back.
    $shop->update(['trial_ends_at' => now()->addDay()]);
    $this->travel(2)->days();
    $user = $user->fresh();
    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('shop.show_trial_ended_notice', true));
});
