<?php

use App\Jobs\PushJobInvoiceToQuickBooks;
use App\Models\Shop;
use App\Services\QuickBooks\QuickBooksClient;
use Database\Seeders\PlanSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

test('signup records the browser\'s timezone and ends the trial at local midnight', function () {
    $this->seed(PlanSeeder::class);
    Carbon::setTestNow('2026-09-24 22:00:00'); // 3pm in Los Angeles

    $this->post('/register', [
        'name' => 'Pat', 'email' => 'pat@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
        'shop_name' => 'West Coast Fab', 'timezone' => 'America/Los_Angeles',
    ]);

    $shop = auth()->user()->currentShop;

    expect($shop->timezone)->toBe('America/Los_Angeles')
        // Oct 8, 23:59:59 Pacific (PDT, UTC-7) = Oct 9, 06:59:59 UTC
        ->and($shop->trial_ends_at->toIso8601String())->toBe('2026-10-09T06:59:59+00:00');
});

test('signup with an unrecognisable timezone falls back to the default instead of failing', function () {
    $this->seed(PlanSeeder::class);

    $this->post('/register', [
        'name' => 'Pat', 'email' => 'pat@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
        'shop_name' => 'Somewhere Fab', 'timezone' => 'Not/AZone',
    ])->assertRedirect(route('dashboard', absolute: false));

    expect(auth()->user()->currentShop->timezone)->toBeNull();
});

test('the expired-trial check runs hourly so each shop hears about it soon after its own midnight', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'app:notify-expired-trials'));

    expect($event->expression)->toBe('0 * * * *');
});

test('the quickbooks invoice is dated on the shop\'s calendar', function () {
    Http::fake([
        '*/query*' => Http::response(['QueryResponse' => []]),
        '*/customer' => Http::response(['Customer' => ['Id' => 'qb-cust-1']]),
        '*/invoice' => Http::response(['Invoice' => ['Id' => 'qb-inv-1']]),
    ]);

    $shop = connectedShop();
    $shop->update(['timezone' => 'America/New_York']);
    $job = jobWithLineItem($shop, ['email' => 'buyer@example.com']);
    // Sep 25, 01:30 UTC is still Sep 24, 9:30pm in New York.
    $job->invoiced_at = Carbon::parse('2026-09-25 01:30:00', 'UTC');
    $job->save();

    (new PushJobInvoiceToQuickBooks($job->fresh()))->handle(app(QuickBooksClient::class));

    Http::assertSent(fn ($request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/invoice')
        && $request['TxnDate'] === '2026-09-24');
    // The conversion must not leak back into the stored UTC value.
    expect($job->fresh()->invoiced_at->toIso8601String())->toBe('2026-09-25T01:30:00+00:00');
});

test('the current shop\'s timezone is shared with every page', function () {
    $shop = Shop::factory()->create(['timezone' => 'Europe/Dublin']);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('shop.timezone', 'Europe/Dublin'));
});
