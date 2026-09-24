<?php

use App\Models\Plan;
use App\Models\Shop;
use App\Notifications\TrialEnded;
use Illuminate\Support\Facades\Notification;

test('it notifies owners of expired trials on the free plan and marks them notified', function () {
    Notification::fake();

    $freePlan = Plan::factory()->create(['key' => 'free']);
    $expiredShop = Shop::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'plan_id' => $freePlan->id,
    ]);

    $this->artisan('app:notify-expired-trials');

    Notification::assertSentTo($expiredShop->owner, TrialEnded::class);
    expect($expiredShop->fresh()->trial_ended_notified_at)->not->toBeNull();
});

test('it does not re-notify a shop that was already notified', function () {
    Notification::fake();

    $freePlan = Plan::factory()->create(['key' => 'free']);
    Shop::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'plan_id' => $freePlan->id,
        'trial_ended_notified_at' => now()->subHour(),
    ]);

    $this->artisan('app:notify-expired-trials');

    Notification::assertNothingSent();
});

test('it does not notify a shop still on trial', function () {
    Notification::fake();

    $freePlan = Plan::factory()->create(['key' => 'free']);
    Shop::factory()->create([
        'trial_ends_at' => now()->addDay(),
        'plan_id' => $freePlan->id,
    ]);

    $this->artisan('app:notify-expired-trials');

    Notification::assertNothingSent();
});

test('it does not notify a shop that upgraded off the free plan', function () {
    Notification::fake();

    $paidPlan = Plan::factory()->create(['key' => 'pro']);
    Shop::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'plan_id' => $paidPlan->id,
    ]);

    $this->artisan('app:notify-expired-trials');

    Notification::assertNothingSent();
});
