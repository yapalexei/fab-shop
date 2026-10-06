<?php

use App\Enums\BillingInterval;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Support\Billing;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Carbon::setTestNow('2026-09-10 12:00:00');
    $this->seed(PlanSeeder::class);
    Notification::fake();
});

afterEach(fn () => Carbon::setTestNow());

/** A paying shop (trial over) on $plan with a live period; the owner takes one seat. */
function seatShop(string $plan = 'start-up', int $extra = 0, bool $period = true): array
{
    $shop = Shop::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'plan_id' => Plan::where('key', $plan)->value('id'),
        'subscription_renews_at' => $period ? '2026-09-25 12:00:00' : null,
        'extra_seats' => $extra,
    ]);

    return [$shop, actingAsShopMember($shop, 'Owner')];
}

/** Top the shop up until $total people hold a seat (the factory owner and the acting user already do). */
function seatsUsed(Shop $shop, int $total): void
{
    $missing = $total - $shop->seatsUsed();
    User::factory()->count(max(0, $missing))->create()->each(fn ($user) => $shop->members()->attach($user));
}

test('the seat limit is the plan\'s seats plus the extras bought', function () {
    [$shop] = seatShop('start-up', extra: 2);

    expect($shop->seatLimit())->toBe(6)
        ->and($shop->seatCapacity())->toBe(6);
});

test('a shop on its trial gets the top plan\'s seats, not the one it picked', function () {
    $shop = Shop::factory()->create(['plan_id' => Plan::where('key', 'solo')->value('id')]);

    expect($shop->seatLimit())->toBe(1)
        ->and($shop->seatCapacity())->toBe(10);
});

test('inviting is refused once every seat is taken, counting pending invitations', function () {
    [$shop, $owner] = seatShop('start-up'); // 4 seats
    seatsUsed($shop, 3);
    Invitation::create(['shop_id' => $shop->id, 'email' => 'a@example.com', 'role' => 'Staff', 'token' => 'x', 'expires_at' => now()->addDay()]);

    $this->actingAs($owner)->post(route('team.invite'), ['email' => 'b@example.com', 'role' => 'Staff'])
        ->assertSessionHasErrors('email');

    expect(Invitation::where('email', 'b@example.com')->exists())->toBeFalse();
});

test('re-inviting an address that already holds a pending seat is allowed when full', function () {
    [$shop, $owner] = seatShop('start-up');
    seatsUsed($shop, 3);
    Invitation::create(['shop_id' => $shop->id, 'email' => 'a@example.com', 'role' => 'Staff', 'token' => 'x', 'expires_at' => now()->addDay()]);

    $this->actingAs($owner)->post(route('team.invite'), ['email' => 'a@example.com', 'role' => 'Admin'])
        ->assertSessionHasNoErrors();

    expect(Invitation::where('email', 'a@example.com')->sole()->role)->toBe('Admin');
});

test('an expired invitation does not hold a seat', function () {
    [$shop, $owner] = seatShop('start-up');
    seatsUsed($shop, 3);
    Invitation::create(['shop_id' => $shop->id, 'email' => 'old@example.com', 'role' => 'Staff', 'token' => 'x', 'expires_at' => now()->subDay()]);

    $this->actingAs($owner)->post(route('team.invite'), ['email' => 'b@example.com', 'role' => 'Staff'])
        ->assertSessionHasNoErrors();
});

test('buying a seat makes room to invite', function () {
    [$shop, $owner] = seatShop('start-up');
    seatsUsed($shop, 4); // full

    $this->actingAs($owner)->post(route('team.invite'), ['email' => 'b@example.com', 'role' => 'Staff'])->assertSessionHasErrors('email');

    $this->actingAs($owner)->put(route('shop.seats.update'), ['extra_seats' => 1])->assertSessionHasNoErrors();
    expect($shop->fresh()->seatLimit())->toBe(5);

    $this->actingAs($owner)->post(route('team.invite'), ['email' => 'b@example.com', 'role' => 'Staff'])->assertSessionHasNoErrors();
});

test('accepting an invitation fails if the shop is over its seats', function () {
    [$shop] = seatShop('start-up');
    seatsUsed($shop, 4); // full
    $invitation = Invitation::create(['shop_id' => $shop->id, 'email' => 'late@example.com', 'role' => 'Staff', 'token' => 'tok', 'expires_at' => now()->addDay()]);

    $this->post(route('invitations.accept', 'tok'), ['name' => 'Late', 'password' => 'password', 'password_confirmation' => 'password', 'accepted_terms' => true])
        ->assertSessionHas('error');

    expect(User::where('email', 'late@example.com')->exists())->toBeFalse()
        ->and($invitation->fresh()->isAccepted())->toBeFalse();
});

test('only the owner can change seats', function () {
    [$shop] = seatShop('start-up');
    $admin = actingAsShopMember($shop, 'Admin');

    $this->actingAs($admin)->put(route('shop.seats.update'), ['extra_seats' => 1])->assertForbidden();
});

test('a plan with no seat price cannot buy extras', function () {
    [$shop, $owner] = seatShop('solo');

    $this->actingAs($owner)->put(route('shop.seats.update'), ['extra_seats' => 1])->assertSessionHasErrors('extra_seats');
    expect($shop->fresh()->extra_seats)->toBe(0);
});

test('seats are billed with a live period, so none can be bought without one', function () {
    [$shop, $owner] = seatShop('start-up', period: false);

    $this->actingAs($owner)->put(route('shop.seats.update'), ['extra_seats' => 1])->assertSessionHasErrors('extra_seats');
});

test('extra seats cannot drop below the people already seated', function () {
    [$shop, $owner] = seatShop('start-up', extra: 2); // 6 seats
    seatsUsed($shop, 6);

    $this->actingAs($owner)->put(route('shop.seats.update'), ['extra_seats' => 1])->assertSessionHasErrors('extra_seats');
    expect($shop->fresh()->extra_seats)->toBe(2);

    $this->actingAs($owner)->put(route('shop.seats.update'), ['extra_seats' => 2])->assertSessionHasNoErrors();
});

test('extra seats are capped', function () {
    [$shop, $owner] = seatShop('start-up');

    $this->actingAs($owner)->put(route('shop.seats.update'), ['extra_seats' => 999])->assertSessionHasErrors('extra_seats');
});

test('a seat quote prorates the added seats for the days left', function () {
    [$shop] = seatShop('start-up');

    $quote = Billing::seatQuote($shop, 2);

    // 2 x $9, 15/31 of the period left.
    expect($quote['charge_cents'])->toBe((int) round(1800 * 15 / 31))
        ->and($quote['period_price_cents'])->toBe(1800)
        ->and($quote['starts_new_period'])->toBeFalse();
});

test('lowering seats is free', function () {
    [$shop] = seatShop('start-up', extra: 3);

    expect(Billing::seatQuote($shop, 1)['charge_cents'])->toBe(0);
});

test('an upgrade carries the shop\'s extra seats into the new price', function () {
    [$shop] = seatShop('start-up', extra: 2);

    $quote = Billing::planChangeQuote($shop, Plan::where('key', 'pro')->first(), BillingInterval::Monthly);

    // Pro $119 + 2 x $9 seats recurring; same $9 seat price, so only the plan difference is charged now.
    expect($quote['period_price_cents'])->toBe(11900 + 1800)
        ->and($quote['charge_cents'])->toBe((int) round(6000 * 15 / 31));
});

test('the plan page shows the seat card with quotes for a shop that can buy', function () {
    [$shop, $owner] = seatShop('start-up');

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('seats.used', 2)
        ->where('seats.included', 4)
        ->where('seats.can_buy', true)
        ->where('seats.price_cents', 900)
        ->has('seats.quotes.1')
        ->has('seats.quotes.10')
        ->missing('seats.quotes.11'));
});

test('seats are isolated per shop', function () {
    [$shopA, $ownerA] = seatShop('start-up');
    [$shopB] = seatShop('start-up');

    $this->actingAs($ownerA)->put(route('shop.seats.update'), ['extra_seats' => 2]);

    expect($shopA->fresh()->extra_seats)->toBe(2)
        ->and($shopB->fresh()->extra_seats)->toBe(0);
});
