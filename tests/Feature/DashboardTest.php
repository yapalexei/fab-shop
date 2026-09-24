<?php

use App\Enums\JobStatus;
use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Quote;
use App\Models\Shop;
use Illuminate\Support\Carbon;

/**
 * A job whose quote totals exactly $amount (no markup), so dashboard sums
 * are easy to assert against.
 */
function pricedJob(Shop $shop, JobStatus $status, float $amount, ?Carbon $invoicedAt = null): Job
{
    $job = jobForShop($shop, $status);
    $job->quote->lineItems()->create([
        'type' => 'material', 'description' => 'Steel', 'quantity' => 1, 'unit_cost' => $amount, 'markup_percent' => 0,
    ]);
    $job->invoiced_at = $invoicedAt;
    $job->save();

    return $job;
}

function pricedQuote(Shop $shop, QuoteStatus $status, float $amount, ?Carbon $sentAt = null): Quote
{
    $customer = Customer::factory()->for($shop)->create();
    $quote = Quote::factory()->for($shop)->create([
        'customer_id' => $customer->id, 'status' => $status, 'sent_at' => $sentAt,
    ]);
    $quote->lineItems()->create([
        'type' => 'material', 'description' => 'Steel', 'quantity' => 1, 'unit_cost' => $amount, 'markup_percent' => 0,
    ]);

    return $quote;
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-24 12:00:00');
});

test('guests are redirected away from the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('a shop with no data gets an empty but valid dashboard', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('kpis.invoiced_this_month', 0)
            ->where('kpis.win_rate', null)
            ->where('attention', [])
            ->where('recentActivity', [])
            ->has('revenueTrend', 6)
            ->has('pipeline', 6));
});

test('invoiced revenue is bucketed by the month a job was invoiced', function () {
    $shop = Shop::factory()->create();
    pricedJob($shop, JobStatus::Invoiced, 1000, now()->startOfMonth()->addDay());
    pricedJob($shop, JobStatus::Invoiced, 500, now());
    pricedJob($shop, JobStatus::Invoiced, 250, now()->subMonthNoOverflow());
    pricedJob($shop, JobStatus::Invoiced, 99, now()->subMonthsNoOverflow(8));
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('kpis.invoiced_this_month', 1500)
            ->where('kpis.invoiced_last_month', 250)
            ->where('revenueTrend.5.month', '2026-09')
            ->where('revenueTrend.5.total', 1500)
            ->where('revenueTrend.4.total', 250)
            ->where('revenueTrend.0.month', '2026-04'));
});

test('a job cancelled after being invoiced no longer counts as revenue', function () {
    $shop = Shop::factory()->create();
    pricedJob($shop, JobStatus::Cancelled, 1000, now());
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('kpis.invoiced_this_month', 0));
});

test('work in progress, ready to invoice and open quotes are summed by stage', function () {
    $shop = Shop::factory()->create();
    pricedJob($shop, JobStatus::Cutting, 100);
    pricedJob($shop, JobStatus::Finishing, 200);
    pricedJob($shop, JobStatus::Shipped, 400);
    pricedJob($shop, JobStatus::Cancelled, 800);
    pricedQuote($shop, QuoteStatus::Sent, 300, now());
    pricedQuote($shop, QuoteStatus::Draft, 5000);
    $archived = pricedQuote($shop, QuoteStatus::Sent, 7000, now());
    $archived->update(['archived_at' => now()]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('kpis.work_in_progress', ['count' => 2, 'value' => 300])
            ->where('kpis.ready_to_invoice', ['count' => 1, 'value' => 400])
            ->where('kpis.open_quotes', ['count' => 1, 'value' => 300])
            ->where('pipeline.1', ['status' => 'cutting', 'label' => 'Cutting', 'count' => 1])
            ->where('pipeline.5', ['status' => 'shipped', 'label' => 'Shipped', 'count' => 1]));
});

test('win rate only counts quotes decided within the window', function () {
    $shop = Shop::factory()->create();
    pricedQuote($shop, QuoteStatus::Won, 100, now()->subDays(10));
    pricedQuote($shop, QuoteStatus::Won, 100, now()->subDays(20));
    pricedQuote($shop, QuoteStatus::Lost, 100, now()->subDays(30));
    pricedQuote($shop, QuoteStatus::Lost, 100, now()->subDays(120));
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('kpis.win_rate', 67));
});

test('needs attention flags sync failures, unbilled shipped jobs and stale quotes', function () {
    $shop = Shop::factory()->create();
    $shipped = pricedJob($shop, JobStatus::Shipped, 400);
    $failed = pricedJob($shop, JobStatus::Invoiced, 900, now());
    $failed->quickbooks_sync_error = 'Token revoked';
    $failed->save();
    pricedQuote($shop, QuoteStatus::Sent, 300, now()->subDays(10));
    pricedQuote($shop, QuoteStatus::Sent, 300, now()->subDays(2));
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('attention', 3)
            ->where('attention.0.kind', 'sync_error')
            ->where('attention.0.subtitle', 'Token revoked')
            ->where('attention.1.kind', 'ready_to_invoice')
            ->where('attention.1.href', route('jobs.show', $shipped))
            ->where('attention.2.kind', 'stale_quote'));
});

test('recent activity lists the shop\'s job history, newest first', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, JobStatus::Cutting);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), ['status' => 'fitting']);
    Carbon::setTestNow(now()->addMinute());
    $this->actingAs($user)->patch(route('jobs.status', $job), ['status' => 'welding']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('recentActivity', 2)
            ->where('recentActivity.0.description', 'Status changed from Fitting to Welding')
            ->where('recentActivity.0.job_number', $job->job_number)
            ->where('recentActivity.0.causer', $user->name));
});

test('the dashboard never includes another shop\'s jobs, quotes or activity', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $otherJob = pricedJob($shopB, JobStatus::Invoiced, 1000, now());
    pricedJob($shopB, JobStatus::Shipped, 400);
    pricedQuote($shopB, QuoteStatus::Sent, 300, now()->subDays(30));
    activity()->performedOn($otherJob)->log('Status changed from Shipped to Invoiced');
    $userA = actingAsShopMember($shopA);

    $this->actingAs($userA)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('kpis.invoiced_this_month', 0)
            ->where('kpis.ready_to_invoice', ['count' => 0, 'value' => 0])
            ->where('kpis.open_quotes', ['count' => 0, 'value' => 0])
            ->where('attention', [])
            ->where('recentActivity', []));
});

test('moving a job to invoiced records when it was invoiced', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, JobStatus::Shipped);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), ['status' => 'invoiced']);

    expect($job->fresh()->invoiced_at->equalTo(now()))->toBeTrue();
});
