<?php

use App\Models\Job;
use App\Models\Quote;
use App\Models\Shop;
use App\Models\User;

beforeEach(function () {
    config(['app.mode' => 'demo']);
});

test('demo commands refuse to run when APP_MODE is not demo', function () {
    config(['app.mode' => null]);

    $this->artisan('demo:seed-team')->assertExitCode(1);
    $this->artisan('demo:seed-multi-shop')->assertExitCode(1);

    expect(Shop::count())->toBe(0);
});

test('demo commands refuse to run in production regardless of APP_MODE', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('demo:seed-team')->assertExitCode(1);

    expect(Shop::count())->toBe(0);
});

test('demo:seed-team builds a full shop with team, catalog, quote, and job', function () {
    $this->artisan('demo:seed-team')->assertExitCode(0);

    $shop = Shop::where('name', 'Ironclad Fabrication (Demo)')->firstOrFail();

    expect($shop->members)->toHaveCount(4)
        ->and(User::where('email', 'owner@ironclad.demo')->exists())->toBeTrue()
        ->and(User::where('email', 'welder@ironclad.demo')->exists())->toBeTrue();

    $quote = Quote::where('shop_id', $shop->id)->firstOrFail();
    expect($quote->lineItems)->toHaveCount(4)
        ->and($quote->status->value)->toBe('won');

    $job = Job::where('shop_id', $shop->id)->firstOrFail();
    expect($job->activitiesAsSubject)->not->toBeEmpty();
});

test('demo:seed-team refuses to duplicate an existing demo shop without --fresh', function () {
    $this->artisan('demo:seed-team')->assertExitCode(0);
    $this->artisan('demo:seed-team')->assertExitCode(1);

    expect(Shop::where('name', 'Ironclad Fabrication (Demo)')->count())->toBe(1);
});

test('demo:seed-team --fresh replaces the shop instead of duplicating it', function () {
    $this->artisan('demo:seed-team')->assertExitCode(0);
    $firstShopId = Shop::where('name', 'Ironclad Fabrication (Demo)')->value('id');

    $this->artisan('demo:seed-team --fresh')->assertExitCode(0);

    expect(Shop::where('name', 'Ironclad Fabrication (Demo)')->count())->toBe(1)
        ->and(Shop::where('name', 'Ironclad Fabrication (Demo)')->value('id'))->not->toBe($firstShopId);
});

test('demo:seed-multi-shop builds two isolated shops and a user who belongs to both', function () {
    $this->artisan('demo:seed-multi-shop')->assertExitCode(0);

    $shopA = Shop::where('name', 'Bayview Welding (Demo)')->firstOrFail();
    $shopB = Shop::where('name', 'Summit Ornamental Iron (Demo)')->firstOrFail();

    $roamer = User::where('email', 'roamer@demo.test')->firstOrFail();

    expect($roamer->shops->pluck('id')->sort()->values()->all())
        ->toBe(collect([$shopA->id, $shopB->id])->sort()->values()->all());

    expect(Quote::where('shop_id', $shopA->id)->exists())->toBeTrue()
        ->and(Quote::where('shop_id', $shopB->id)->exists())->toBeTrue();
});

test('demo:seed-multi-shop --fresh keeps the roamer intact across a reseed', function () {
    $this->artisan('demo:seed-multi-shop')->assertExitCode(0);
    $this->artisan('demo:seed-multi-shop --fresh')->assertExitCode(0);

    $roamer = User::where('email', 'roamer@demo.test')->firstOrFail();

    expect($roamer->shops)->toHaveCount(2)
        ->and(User::where('email', 'roamer@demo.test')->count())->toBe(1);
});

// Seeding writes jobs directly, bypassing JobStatusController::recordPeriod,
// so the seeder has to set the period itself - a job with no started_at is
// invisible on the job timeline and calendar.
test('demo:seed-team gives every started job a period, so the job timeline has something to show', function () {
    $this->artisan('demo:seed-team')->assertExitCode(0);

    $shop = Shop::where('name', 'Ironclad Fabrication (Demo)')->firstOrFail();
    $jobs = Job::where('shop_id', $shop->id)->with('jobStage')->get();

    foreach ($jobs as $job) {
        $role = $job->jobStage->role?->value;

        if ($role === 'not_started') {
            expect($job->started_at)->toBeNull()->and($job->finished_at)->toBeNull();

            continue;
        }

        expect($job->started_at)->not->toBeNull("{$job->job_number} left Not started but has no started_at");

        if (in_array($role, ['shipped', 'invoiced'], true)) {
            expect($job->finished_at)->not->toBeNull()
                ->and($job->finished_at->gte($job->started_at))->toBeTrue();
        } else {
            expect($job->finished_at)->toBeNull();
        }
    }

    // And the report actually lists them (cancelled jobs are left out).
    $owner = User::where('email', 'owner@ironclad.demo')->firstOrFail();
    $listed = $this->actingAs($owner)
        ->get(route('reports.job-timeline', ['range' => 'month', 'date' => now()->subDays(20)->toDateString()]))
        ->assertOk()
        ->viewData('page')['props']['jobs'];

    expect($listed)->not->toBeEmpty();
});
