<?php

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Shop;
use App\Models\User;
use App\Support\ShopPermissions;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-10-14 15:00:00'));
afterEach(fn () => Carbon::setTestNow());

test('a member with jobs.update can set, change and clear a due date, and it is logged', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.due-date.update', $job), ['due_date' => '2026-10-20'])->assertRedirect();
    expect($job->refresh()->due_date->toDateString())->toBe('2026-10-20')
        ->and($job->activitiesAsSubject()->latest('id')->first()->description)->toBe('Due date set to Oct 20, 2026');

    $this->actingAs($user)->patch(route('jobs.due-date.update', $job), ['due_date' => null])->assertRedirect();
    expect($job->refresh()->due_date)->toBeNull();
});

test('a due date must be a valid date', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);

    $this->actingAs(actingAsShopMember($shop))
        ->patch(route('jobs.due-date.update', $job), ['due_date' => 'next friday'])
        ->assertSessionHasErrors('due_date');
});

test('a user without jobs.update cannot set a due date', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);

    $user = User::factory()->create(['current_shop_id' => $shop->id]);
    $shop->members()->attach($user);
    ShopPermissions::seedRoles($shop);

    $this->actingAs($user)
        ->patch(route('jobs.due-date.update', $job), ['due_date' => '2026-10-20'])
        ->assertForbidden();
});

test('another shop\'s job cannot have its due date changed', function () {
    $job = jobForShop(Shop::factory()->create());

    $this->actingAs(actingAsShopMember(Shop::factory()->create()))
        ->patch(route('jobs.due-date.update', $job), ['due_date' => '2026-10-20'])
        ->assertNotFound();
});

test('a job is overdue only when open, uncancelled and past due in the shop\'s own today', function () {
    $shop = Shop::factory()->create(['timezone' => 'America/Los_Angeles']);
    $today = $shop->localNow(); // still the 14th in LA

    $job = jobForShop($shop, 'in_progress');
    $job->due_date = '2026-10-14';
    expect($job->isOverdue($today))->toBeFalse(); // due today isn't overdue yet

    $job->due_date = '2026-10-13';
    expect($job->isOverdue($today))->toBeTrue();

    $job->finished_at = now();
    expect($job->isOverdue($today))->toBeFalse(); // shipped

    $job->finished_at = null;
    $job->cancelled_at = now();
    expect($job->isOverdue($today))->toBeFalse();

    $job->cancelled_at = null;
    $job->due_date = null;
    expect($job->isOverdue($today))->toBeFalse();
});

test('the board and job page expose the due date and overdue flag', function () {
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    $job = jobForShop($shop, 'in_progress');
    $job->forceFill(['due_date' => '2026-10-10', 'started_at' => '2026-10-01 10:00:00'])->save();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('jobs.index'))
        ->assertInertia(fn ($page) => $page->where('jobs.0.due_date', '2026-10-10')->where('jobs.0.overdue', true));
    $this->actingAs($user)->get(route('jobs.show', $job))
        ->assertInertia(fn ($page) => $page->where('job.due_date', '2026-10-10')->where('job.overdue', true)->where('job.can_edit', true));
});

test('the timeline can show only overdue jobs', function () {
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    $late = jobForShop($shop, 'in_progress');
    $late->forceFill(['started_at' => '2026-10-01 10:00:00', 'due_date' => '2026-10-10'])->save();
    $onTime = jobForShop($shop, 'in_progress');
    $onTime->forceFill(['started_at' => '2026-10-01 10:00:00', 'due_date' => '2026-10-30'])->save();
    $shippedLate = jobForShop($shop, 'shipped');
    $shippedLate->forceFill(['started_at' => '2026-10-01 10:00:00', 'finished_at' => '2026-10-12 10:00:00', 'due_date' => '2026-10-05'])->save();

    $this->actingAs(actingAsShopMember($shop))
        ->get(route('reports.job-timeline', ['range' => 'month', 'date' => '2026-10-14', 'overdue' => 1]))
        ->assertInertia(fn ($page) => $page->has('jobs', 1)->where('jobs.0.job_number', $late->job_number)->where('jobs.0.due_date', '2026-10-10')->where('filters.overdue', true));
});

test('a quote\'s due date is saved, and carried onto the job when converted', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $quote = Quote::factory()->for($shop)->create([
        'customer_id' => Customer::factory()->for($shop)->create()->id,
        'status' => QuoteStatus::Won,
        'due_date' => '2026-11-03',
    ]);

    $this->actingAs($user)->post(route('quotes.convert', $quote))->assertRedirect();

    expect($quote->job->due_date->toDateString())->toBe('2026-11-03');
});

test('a quote converts fine without a due date, and the date is editable on the quote', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $quote = Quote::factory()->for($shop)->create([
        'customer_id' => Customer::factory()->for($shop)->create()->id,
        'status' => QuoteStatus::Won,
    ]);

    $this->actingAs($user)->post(route('quotes.convert', $quote))->assertRedirect();
    expect($quote->job->due_date)->toBeNull();

    $draft = Quote::factory()->for($shop)->create(['customer_id' => $quote->customer_id]);
    $this->actingAs($user)->put(route('quotes.update', $draft), [
        'customer_id' => $draft->customer_id,
        'due_date' => '2026-12-01',
        'line_items' => [['type' => 'material', 'description' => 'Steel', 'quantity' => 1, 'unit_cost' => 10, 'markup_percent' => 0]],
    ])->assertRedirect();
    expect($draft->refresh()->due_date->toDateString())->toBe('2026-12-01');

    $this->actingAs($user)->get(route('quotes.edit', $draft))
        ->assertInertia(fn ($page) => $page->where('quote.due_date', '2026-12-01'));
});
