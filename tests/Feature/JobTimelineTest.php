<?php

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
