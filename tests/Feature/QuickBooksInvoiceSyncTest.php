<?php

use App\Enums\Feature;
use App\Enums\JobStageRole;
use App\Jobs\PushJobInvoiceToQuickBooks;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Quote;
use App\Models\Shop;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    if (! Feature::QuickbooksSync->isShipped()) {
        $this->markTestSkipped('QuickBooks sync is switched off (future feature) - see Feature::isShipped().');
    }
});

function connectedShop(): Shop
{
    $shop = Shop::factory()->create();
    $shop->quickBooksConnection()->create([
        'realm_id' => 'realm-1',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDays(90),
        'connected_at' => now(),
    ]);

    return $shop;
}

/**
 * @param  array<string, mixed>  $customerAttrs
 */
function jobWithLineItem(Shop $shop, array $customerAttrs = []): Job
{
    $customer = Customer::factory()->for($shop)->create($customerAttrs);
    $quote = Quote::factory()->for($shop)->create(['customer_id' => $customer->id]);
    $quote->lineItems()->create([
        'type' => 'material', 'description' => 'Steel plate',
        'quantity' => 2, 'unit_cost' => 50, 'markup_percent' => 20,
    ]);

    $invoiced = JobStage::query()->where('shop_id', $shop->id)->where('role', JobStageRole::Invoiced)->firstOrFail();

    $job = new Job;
    $job->forceFill([
        'shop_id' => $shop->id, 'quote_id' => $quote->id, 'customer_id' => $customer->id,
        'job_number' => 'J-0001', 'job_stage_id' => $invoiced->id,
    ])->save();

    return $job;
}

test('pushing an invoice creates a new quickbooks customer when none exists yet', function () {
    Http::fake([
        '*/query*' => Http::response(['QueryResponse' => []]),
        '*/customer' => Http::response(['Customer' => ['Id' => 'qb-cust-1']]),
        '*/invoice' => Http::response(['Invoice' => ['Id' => 'qb-inv-1']]),
    ]);

    $shop = connectedShop();
    $job = jobWithLineItem($shop, ['email' => 'buyer@example.com']);

    (new PushJobInvoiceToQuickBooks($job))->handle(app(QuickBooksClient::class));

    expect($job->customer->fresh()->quickbooks_customer_id)->toBe('qb-cust-1')
        ->and($job->fresh()->quickbooks_invoice_id)->toBe('qb-inv-1')
        ->and($job->fresh()->quickbooks_synced_at)->not->toBeNull();

    Http::assertSent(fn ($request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/customer') && $request->method() === 'POST');
});

test('a customer that already exists in quickbooks is linked, not duplicated', function () {
    Http::fake([
        '*/query*' => Http::response(['QueryResponse' => ['Customer' => [['Id' => 'existing-cust']]]]),
        '*/invoice' => Http::response(['Invoice' => ['Id' => 'qb-inv-1']]),
    ]);

    $shop = connectedShop();
    $job = jobWithLineItem($shop, ['email' => 'buyer@example.com']);

    (new PushJobInvoiceToQuickBooks($job))->handle(app(QuickBooksClient::class));

    expect($job->customer->fresh()->quickbooks_customer_id)->toBe('existing-cust');

    Http::assertNotSent(fn ($request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/customer') && $request->method() === 'POST');
});

test('a customer already linked from a previous sync is not looked up again', function () {
    Http::fake([
        '*/invoice' => Http::response(['Invoice' => ['Id' => 'qb-inv-1']]),
    ]);

    $shop = connectedShop();
    $job = jobWithLineItem($shop, ['email' => 'buyer@example.com', 'quickbooks_customer_id' => 'already-linked']);

    (new PushJobInvoiceToQuickBooks($job))->handle(app(QuickBooksClient::class));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/query'));
});

test('an expired access token is refreshed before syncing', function () {
    Http::fake([
        '*/tokens/bearer' => Http::response([
            'access_token' => 'new-access', 'refresh_token' => 'new-refresh',
            'expires_in' => 3600, 'x_refresh_token_expires_in' => 8726400,
        ]),
        '*/query*' => Http::response(['QueryResponse' => []]),
        '*/customer' => Http::response(['Customer' => ['Id' => 'qb-cust-1']]),
        '*/invoice' => Http::response(['Invoice' => ['Id' => 'qb-inv-1']]),
    ]);

    $shop = Shop::factory()->create();
    $shop->quickBooksConnection()->create([
        'realm_id' => 'realm-1', 'access_token' => 'stale-token', 'refresh_token' => 'refresh-token',
        'expires_at' => now()->subMinute(), 'refresh_token_expires_at' => now()->addDays(90), 'connected_at' => now(),
    ]);
    $job = jobWithLineItem($shop, ['email' => 'buyer@example.com']);

    (new PushJobInvoiceToQuickBooks($job))->handle(app(QuickBooksClient::class));

    expect($shop->quickBooksConnection->fresh()->access_token)->toBe('new-access');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/tokens/bearer'));
});

test('a job push with no quickbooks connection is a silent no-op', function () {
    Http::fake();

    $shop = Shop::factory()->create();
    $job = jobWithLineItem($shop);

    (new PushJobInvoiceToQuickBooks($job))->handle(app(QuickBooksClient::class));

    expect($job->fresh()->quickbooks_invoice_id)->toBeNull();
    Http::assertNothingSent();
});

test('a failed invoice push records the error and rethrows so the queue retries it', function () {
    Http::fake([
        '*/query*' => Http::response(['QueryResponse' => []]),
        '*/customer' => Http::response(['Customer' => ['Id' => 'qb-cust-1']]),
        '*/invoice' => Http::response('Server error', 500),
    ]);

    $shop = connectedShop();
    $job = jobWithLineItem($shop, ['email' => 'buyer@example.com']);

    expect(fn () => (new PushJobInvoiceToQuickBooks($job))->handle(app(QuickBooksClient::class)))
        ->toThrow(RuntimeException::class);

    expect($job->fresh()->quickbooks_sync_error)->not->toBeNull()
        ->and($job->fresh()->quickbooks_invoice_id)->toBeNull();
});

test('marking a job invoiced dispatches the quickbooks push', function () {
    Queue::fake();

    $shop = connectedShop();
    $job = jobWithLineItem($shop, ['email' => 'buyer@example.com']);
    $shipped = JobStage::query()->where('shop_id', $shop->id)->where('role', JobStageRole::Shipped)->firstOrFail();
    $invoiced = JobStage::query()->where('shop_id', $shop->id)->where('role', JobStageRole::Invoiced)->firstOrFail();
    $job->forceFill(['job_stage_id' => $shipped->id])->save();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->patch(route('jobs.status', $job), ['job_stage_id' => $invoiced->id])->assertRedirect();

    Queue::assertPushed(PushJobInvoiceToQuickBooks::class, fn ($pushed) => $pushed->fabricationJob->is($job));
});
