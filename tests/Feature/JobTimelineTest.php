<?php

use App\Models\QuoteLineItem;
use App\Models\Shop;
use App\Models\User;
use App\Support\ShopPermissions;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-10-14 15:00:00'));
afterEach(fn () => Carbon::setTestNow());

function periodJob(Shop $shop, ?string $started, ?string $finished = null, string $stage = 'in_progress')
{
    $job = jobForShop($shop, $stage);
    $job->forceFill(['started_at' => $started, 'finished_at' => $finished])->save();

    return $job;
}

test('leaving not started records started_at, and reaching shipped records finished_at', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, 'not_started');
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), ['job_stage_id' => resolveJobStage($shop, 'in_progress')->id]);
    expect($job->refresh()->started_at)->not->toBeNull()->and($job->finished_at)->toBeNull();

    $startedAt = $job->started_at;
    Carbon::setTestNow('2026-10-16 09:00:00');

    $this->actingAs($user)->patch(route('jobs.status', $job), ['job_stage_id' => resolveJobStage($shop, 'shipped')->id]);
    expect($job->refresh()->started_at->equalTo($startedAt))->toBeTrue()
        ->and($job->finished_at->toDateString())->toBe('2026-10-16');
});

test('skipping straight to shipped starts and finishes the job together', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, 'not_started');

    $this->actingAs(actingAsShopMember($shop))->patch(route('jobs.status', $job), ['job_stage_id' => resolveJobStage($shop, 'shipped')->id]);

    expect($job->refresh()->started_at)->not->toBeNull()->and($job->finished_at)->not->toBeNull();
});

test('moving back to not started clears started_at', function () {
    $shop = Shop::factory()->create();
    $job = periodJob($shop, '2026-10-10 12:00:00');

    $this->actingAs(actingAsShopMember($shop))->patch(route('jobs.status', $job), ['job_stage_id' => resolveJobStage($shop, 'not_started')->id]);

    expect($job->refresh()->started_at)->toBeNull();
});

test('the board payload carries shop-local start and end days, null when not started', function () {
    $shop = Shop::factory()->create(['timezone' => 'America/Los_Angeles']);
    // 03:00 UTC on the 11th is still the evening of the 10th in Los Angeles.
    periodJob($shop, '2026-10-11 03:00:00', '2026-10-12 20:00:00');
    jobForShop($shop, 'not_started');

    $this->actingAs(actingAsShopMember($shop))->get(route('jobs.index'))
        ->assertInertia(fn ($page) => $page->where('today', '2026-10-14')
            ->has('jobs', 2)
            ->where('jobs.0.started_on', null)
            ->where('jobs.1.started_on', '2026-10-10')
            ->where('jobs.1.ended_on', '2026-10-12'));
});

test('the timeline lists only started, uncancelled jobs overlapping the range', function () {
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    $inside = periodJob($shop, '2026-10-05 10:00:00', '2026-10-08 10:00:00', 'shipped');
    $ongoing = periodJob($shop, '2026-09-20 10:00:00');
    periodJob($shop, '2026-09-01 10:00:00', '2026-09-10 10:00:00', 'shipped'); // ended before October
    periodJob($shop, '2026-11-02 10:00:00'); // starts after October
    jobForShop($shop, 'not_started'); // no period at all
    $cancelled = periodJob($shop, '2026-10-02 10:00:00');
    $cancelled->forceFill(['cancelled_at' => now()])->save();

    $this->actingAs(actingAsShopMember($shop))
        ->get(route('reports.job-timeline', ['range' => 'month', 'date' => '2026-10-14']))
        ->assertInertia(fn ($page) => $page->component('Reports/JobTimeline')
            ->where('period', ['start' => '2026-10-01', 'end' => '2026-10-31'])
            ->has('jobs', 2)
            ->where('jobs.0.job_number', $ongoing->job_number)
            ->where('jobs.0.ended_on', null)
            ->where('jobs.1.job_number', $inside->job_number));
});

test('the week range is Monday to Sunday', function () {
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    periodJob($shop, '2026-10-05 10:00:00', '2026-10-06 10:00:00', 'shipped');
    periodJob($shop, '2026-10-01 10:00:00', '2026-10-02 10:00:00', 'shipped');

    $this->actingAs(actingAsShopMember($shop))
        ->get(route('reports.job-timeline', ['range' => 'week', 'date' => '2026-10-14']))
        ->assertInertia(fn ($page) => $page->where('period', ['start' => '2026-10-12', 'end' => '2026-10-18'])->has('jobs', 0));
});

test('the timeline can include only chosen stages', function () {
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    $working = periodJob($shop, '2026-10-05 10:00:00');
    periodJob($shop, '2026-10-05 10:00:00', '2026-10-06 10:00:00', 'shipped');

    $this->actingAs(actingAsShopMember($shop))
        ->get(route('reports.job-timeline', ['range' => 'month', 'date' => '2026-10-14', 'stages' => [$working->job_stage_id]]))
        ->assertInertia(fn ($page) => $page->has('jobs', 1)->where('jobs.0.job_number', $working->job_number)
            ->where('filters.stages', [(string) $working->job_stage_id])
            // Not Started has no period, so it's never offered as a filter.
            ->has('stages', 3));
});

test('the timeline never shows another shop\'s jobs', function () {
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    $other = Shop::factory()->create(['timezone' => 'UTC']);
    periodJob($other, '2026-10-05 10:00:00');

    $this->actingAs(actingAsShopMember($shop))
        ->get(route('reports.job-timeline', ['range' => 'month', 'date' => '2026-10-14']))
        ->assertInertia(fn ($page) => $page->has('jobs', 0));
});

test('the timeline needs jobs.view', function () {
    $shop = Shop::factory()->create();
    $user = User::factory()->create(['current_shop_id' => $shop->id]);
    $shop->members()->attach($user);
    ShopPermissions::seedRoles($shop);

    $this->actingAs($user)->get(route('reports.job-timeline'))->assertForbidden();
});

// The page re-requests itself on every click with its filters in the query
// string, so booleans arrive as the text "true"/"false" - not 1/0. Rejecting
// that made every button on the page silently do nothing.
test('the page accepts the true/false query strings the browser sends', function () {
    Carbon::setTestNow('2026-10-14 09:00:00');
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    $late = periodJob($shop, '2026-10-01 10:00:00');
    $late->forceFill(['due_date' => '2026-10-05'])->save();
    $onTime = periodJob($shop, '2026-10-02 10:00:00');
    $onTime->forceFill(['due_date' => '2026-12-01'])->save();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get('/reports/job-timeline?range=month&date=2026-10-14&overdue=false')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.overdue', false)->has('jobs', 2));

    $this->actingAs($user)->get('/reports/job-timeline?range=week&date=2026-10-14&overdue=true')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.overdue', true)->has('jobs', 1)->where('jobs.0.job_number', $late->job_number));

    // The old 1/0 form keeps working.
    $this->actingAs($user)->get('/reports/job-timeline?overdue=1')->assertOk();
});

test('the year range covers January 1 to December 31 of the chosen date\'s year', function () {
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    $early = periodJob($shop, '2026-01-05 10:00:00', '2026-01-20 10:00:00', 'shipped');
    $late = periodJob($shop, '2026-12-28 10:00:00');
    $spansNewYear = periodJob($shop, '2025-12-20 10:00:00', '2026-01-03 10:00:00', 'shipped');
    periodJob($shop, '2025-03-01 10:00:00', '2025-03-10 10:00:00', 'shipped'); // all last year
    periodJob($shop, '2027-01-02 10:00:00'); // starts next year

    $this->actingAs(actingAsShopMember($shop))
        ->get(route('reports.job-timeline', ['range' => 'year', 'date' => '2026-10-14']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Reports/JobTimeline')
            ->where('filters.range', 'year')
            ->where('period', ['start' => '2026-01-01', 'end' => '2026-12-31'])
            ->has('jobs', 3)
            ->where('jobs', fn ($jobs) => collect($jobs)->pluck('job_number')->sort()->values()->all()
                === collect([$early, $late, $spansNewYear])->pluck('job_number')->sort()->values()->all()));
});

test('an unknown range is still rejected', function () {
    $this->actingAs(actingAsShopMember(Shop::factory()->create()))
        ->get('/reports/job-timeline?range=decade')
        ->assertSessionHasErrors('range');
});

function pricedPeriodJob(Shop $shop, float $qty, float $unitCost, float $markup, string $started, ?string $due = null)
{
    $job = periodJob($shop, $started);
    $job->forceFill(['due_date' => $due])->save();
    QuoteLineItem::factory()->create([
        'quote_id' => $job->quote_id,
        'quantity' => $qty,
        'unit_cost' => $unitCost,
        'markup_percent' => $markup,
        'override_total' => null,
    ]);

    return $job;
}

test('with financials access the timeline shows each job\'s amount and profit and the totals', function () {
    Carbon::setTestNow('2026-10-14 09:00:00');
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    $a = pricedPeriodJob($shop, 2, 50, 20, '2026-10-01 10:00:00');   // price 120, cost 100, profit 20
    $b = pricedPeriodJob($shop, 1, 200, 50, '2026-10-02 10:00:00');  // price 300, cost 200, profit 100
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->get(route('reports.job-timeline', ['range' => 'month', 'date' => '2026-10-14']))
        ->assertInertia(fn ($page) => $page
            ->where('financials', ['jobs' => 2, 'amount' => 420, 'cost' => 300, 'profit' => 120, 'margin_percent' => 28.6])
            ->where('jobs.0.job_number', $a->job_number)
            ->where('jobs.0.amount', 120)
            ->where('jobs.0.profit', 20)
            ->where('jobs.1.amount', 300)
            ->where('jobs.1.profit', 100));
});

test('the totals follow the filters', function () {
    Carbon::setTestNow('2026-10-14 09:00:00');
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    pricedPeriodJob($shop, 1, 100, 0, '2026-10-01 10:00:00', '2026-10-05'); // overdue: price 100
    pricedPeriodJob($shop, 1, 500, 0, '2026-10-02 10:00:00', '2026-12-01'); // on time: price 500
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->get(route('reports.job-timeline', ['range' => 'month', 'date' => '2026-10-14', 'overdue' => 'true']))
        ->assertInertia(fn ($page) => $page->where('financials.jobs', 1)->where('financials.amount', 100));

    $this->actingAs($owner)->get(route('reports.job-timeline', ['range' => 'month', 'date' => '2026-10-14']))
        ->assertInertia(fn ($page) => $page->where('financials.jobs', 2)->where('financials.amount', 600));
});

test('without financials access no money is sent at all', function () {
    Carbon::setTestNow('2026-10-14 09:00:00');
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    pricedPeriodJob($shop, 1, 4321, 0, '2026-10-01 10:00:00');
    $staff = actingAsShopRole($shop, 'Staff');

    expect($staff->hasShopPermission('financials.view'))->toBeFalse();

    $response = $this->actingAs($staff)->get(route('reports.job-timeline', ['range' => 'month', 'date' => '2026-10-14']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('financials', null)
            ->has('jobs', 1)
            ->missing('jobs.0.amount')
            ->missing('jobs.0.profit'));

    // Not in the page source either (Inertia props are readable there).
    expect(json_encode($response->viewData('page')))->not->toContain('4321');
});

test('a loss shows as negative profit', function () {
    Carbon::setTestNow('2026-10-14 09:00:00');
    $shop = Shop::factory()->create(['timezone' => 'UTC']);
    $job = pricedPeriodJob($shop, 1, 100, 0, '2026-10-01 10:00:00');
    $job->quote->lineItems()->update(['override_total' => 70]); // sold below cost

    $this->actingAs(actingAsShopMember($shop))->get(route('reports.job-timeline', ['range' => 'month', 'date' => '2026-10-14']))
        ->assertInertia(fn ($page) => $page->where('jobs.0.profit', -30)->where('financials.profit', -30)->where('financials.margin_percent', -42.9));
});
