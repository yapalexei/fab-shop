<?php

use App\Actions\GenerateQuotePdf;
use App\Enums\QuoteStatus;
use App\Events\JobBoardUpdated;
use App\Jobs\GenerateAndSendQuotePdf;
use App\Mail\QuoteReadyMail;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Quote;
use App\Models\Shop;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

function draftQuoteWithLineItem(Shop $shop, ?string $customerEmail = 'customer@example.com'): Quote
{
    // Emailing is off by default; most workflow tests exercise it.
    $shop->update(['quote_emailing_enabled' => true]);
    $customer = Customer::factory()->for($shop)->create(['email' => $customerEmail]);
    $quote = Quote::factory()->for($shop)->create(['customer_id' => $customer->id]);
    $quote->lineItems()->create([
        'type' => 'material', 'description' => 'Item',
        'quantity' => 1, 'unit_cost' => 10, 'markup_percent' => 0,
    ]);

    return $quote;
}

test('sending a draft quote marks it sent and dispatches pdf generation', function () {
    Bus::fake();

    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.send', $quote))->assertRedirect();

    expect($quote->refresh()->status)->toBe(QuoteStatus::Sent)
        ->and($quote->sent_at)->not->toBeNull();

    Bus::assertDispatched(GenerateAndSendQuotePdf::class, fn ($job) => $job->quote->is($quote));
});

test('a quote with no line items cannot be sent', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $quote = Quote::factory()->for($shop)->create(['customer_id' => $customer->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.send', $quote))->assertSessionHasErrors('status');

    expect($quote->refresh()->status)->toBe(QuoteStatus::Draft);
});

test('a quote with no customer email cannot be sent', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop, null);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.send', $quote))->assertSessionHasErrors('status');
});

test('an already-sent quote cannot be sent again', function () {
    Bus::fake();

    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now()]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.send', $quote))->assertSessionHasErrors('status');
});

test('generating and sending the pdf attaches it to the quote and emails the customer', function () {
    Mail::fake();

    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop, 'buyer@example.com');

    (new GenerateAndSendQuotePdf($quote))->handle(app(GenerateQuotePdf::class));

    expect($quote->fresh()->pdf())->not->toBeNull()
        ->and($quote->fresh()->emailed_at)->not->toBeNull();

    Mail::assertQueued(QuoteReadyMail::class, fn ($mail) => $mail->hasTo('buyer@example.com') && $mail->quote->is($quote));
});

test('only a sent quote can be marked won or lost', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.mark-won', $quote))->assertSessionHasErrors('status');
    $this->actingAs($user)->post(route('quotes.mark-lost', $quote))->assertSessionHasErrors('status');

    expect($quote->refresh()->status)->toBe(QuoteStatus::Draft);
});

test('a sent quote can be marked won', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now()]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.mark-won', $quote))->assertRedirect();

    expect($quote->refresh()->status)->toBe(QuoteStatus::Won);
});

test('only a lost quote can be archived, and it can be reopened as a draft', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now()]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.archive', $quote))->assertSessionHasErrors('status');

    $quote->update(['status' => QuoteStatus::Lost]);

    $this->actingAs($user)->post(route('quotes.archive', $quote))->assertRedirect(route('quotes.index'));

    expect($quote->refresh()->is_archived)->toBeTrue();

    $this->actingAs($user)->post(route('quotes.reopen', $quote))->assertRedirect(route('quotes.edit', $quote));

    $quote->refresh();
    expect($quote->is_archived)->toBeFalse()
        ->and($quote->status)->toBe(QuoteStatus::Draft);
});

test('a won quote can be converted to a job, and converting again is blocked', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Won]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.convert', $quote))->assertRedirect();

    $job = Job::sole();
    expect($job->quote_id)->toBe($quote->id)
        ->and($job->customer_id)->toBe($quote->customer_id)
        ->and($job->job_number)->not->toBeEmpty();

    $this->actingAs($user)->post(route('quotes.convert', $quote))->assertSessionHasErrors('status');

    expect(Job::count())->toBe(1);
});

test('converting a quote to a job broadcasts a job board update for the shop', function () {
    Event::fake([JobBoardUpdated::class]);

    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Won]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.convert', $quote))->assertRedirect();

    Event::assertDispatched(JobBoardUpdated::class, fn (JobBoardUpdated $event) => $event->shopId === $shop->id);
});

test('a draft or sent quote cannot be converted to a job', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.convert', $quote))->assertSessionHasErrors('status');

    expect(Job::count())->toBe(0);
});

test('a user cannot run workflow actions on another shop\'s quote', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shopB);
    $user = actingAsShopMember($shopA);

    $this->actingAs($user)->post(route('quotes.send', $quote))->assertNotFound();
});

test('sending can be skipped with a reason, without emailing the customer', function () {
    Bus::fake();

    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.skip-send', $quote), ['reason' => 'Customer agreed verbally'])
        ->assertRedirect();

    expect($quote->refresh()->status)->toBe(QuoteStatus::Sent)
        ->and($quote->sent_at)->not->toBeNull()
        ->and($quote->emailed_at)->toBeNull()
        ->and($quote->send_skipped_reason)->toBe('Customer agreed verbally');

    Bus::assertNotDispatched(GenerateAndSendQuotePdf::class);
});

test('skipping the send needs a reason, a draft with line items, and works without a customer email', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop, null);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.skip-send', $quote), ['reason' => ''])->assertSessionHasErrors('reason');
    expect($quote->refresh()->status)->toBe(QuoteStatus::Draft);

    $this->actingAs($user)->post(route('quotes.skip-send', $quote), ['reason' => 'Agreed on the phone'])->assertSessionHasNoErrors();
    expect($quote->refresh()->status)->toBe(QuoteStatus::Sent);

    $this->actingAs($user)->post(route('quotes.skip-send', $quote), ['reason' => 'again'])->assertSessionHasErrors('status');
});

test('a sent quote can be emailed again', function () {
    Bus::fake();

    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now(), 'emailed_at' => now()->subDay()]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.resend', $quote))->assertSessionHasNoErrors();

    Bus::assertDispatched(GenerateAndSendQuotePdf::class, fn ($job) => $job->quote->is($quote));
});

test('a quote whose sending was skipped can still be emailed later', function () {
    Bus::fake();

    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now(), 'send_skipped_reason' => 'Agreed verbally']);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.resend', $quote))->assertSessionHasNoErrors();

    expect($quote->refresh()->send_skipped_reason)->toBeNull();
    Bus::assertDispatched(GenerateAndSendQuotePdf::class);
});

test('once a quote is won or lost the email can no longer be sent', function () {
    Bus::fake();

    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    foreach ([QuoteStatus::Won, QuoteStatus::Lost, QuoteStatus::Draft] as $status) {
        $quote = draftQuoteWithLineItem($shop);
        $quote->update(['status' => $status, 'sent_at' => now()]);

        $this->actingAs($user)->post(route('quotes.resend', $quote))->assertSessionHasErrors('status');
    }

    Bus::assertNotDispatched(GenerateAndSendQuotePdf::class);
});

test('resending needs a customer email on file', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop, null);
    $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now()]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.resend', $quote))->assertSessionHasErrors('status');
});

test('a quote can be previewed as a pdf at any stage without sending or storing anything', function () {
    Bus::fake();
    Mail::fake();

    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $user = actingAsShopMember($shop);

    $response = $this->actingAs($user)->get(route('quotes.preview', $quote))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF')
        ->and($quote->fresh()->status)->toBe(QuoteStatus::Draft)
        ->and($quote->fresh()->pdf())->toBeNull();

    Bus::assertNothingDispatched();
    Mail::assertNothingQueued();
});

test('previewing a quote needs view access and stays within the shop', function () {
    $shop = Shop::factory()->create();
    $other = Shop::factory()->create();
    $foreign = draftQuoteWithLineItem($other);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('quotes.preview', $foreign))->assertNotFound();
});

test('the customer cannot be changed once the quote has been emailed', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now(), 'emailed_at' => now()]);
    $other = Customer::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);

    $payload = fn (int $customerId) => [
        'customer_id' => $customerId,
        'line_items' => [['type' => 'material', 'description' => 'Item', 'quantity' => 1, 'unit_cost' => 10, 'markup_percent' => 0, 'override_total' => null]],
    ];

    $this->actingAs($user)->put(route('quotes.update', $quote), $payload($other->id))->assertSessionHasErrors('customer_id');
    expect($quote->fresh()->customer_id)->not->toBe($other->id);

    // Saving with the same customer is still fine.
    $this->actingAs($user)->put(route('quotes.update', $quote), $payload($quote->customer_id))->assertSessionHasNoErrors();
});

test('the customer can be changed, and the log says why, after the email failed to deliver', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now()]);
    $other = Customer::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);
    $user->currentShop->update(['trial_ends_at' => now()->addDays(5)]);

    (new GenerateAndSendQuotePdf($quote))->failed(new RuntimeException('Mailbox does not exist'));

    $quote->refresh();
    expect($quote->email_failed_at)->not->toBeNull()
        ->and($quote->customer_locked)->toBeFalse();

    $this->actingAs($user)->put(route('quotes.update', $quote), [
        'customer_id' => $other->id,
        'line_items' => [['type' => 'material', 'description' => 'Item', 'quantity' => 1, 'unit_cost' => 10, 'markup_percent' => 0, 'override_total' => null]],
    ])->assertSessionHasNoErrors();

    expect($quote->fresh()->customer_id)->toBe($other->id);

    $summaries = $quote->historyEntries()->pluck('summary')->all();
    expect($summaries)->toContain('Saved changes - customer corrected after the email failed to deliver')
        ->and(collect($summaries)->contains(fn ($s) => str_contains($s, 'failed to deliver - Mailbox does not exist')))->toBeTrue();
});

test('a quote marked sent without emailing can still change customer', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Sent, 'send_skipped_reason' => 'Agreed verbally']);

    expect($quote->customer_locked)->toBeFalse();
});

test('a quote can be duplicated as a fresh draft without attachments', function () {
    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $quote->update(['status' => QuoteStatus::Won, 'notes' => 'Rush', 'due_date' => '2026-12-01', 'sent_at' => now(), 'emailed_at' => now()]);
    $quote->addMedia(UploadedFile::fake()->create('plan.pdf', 10))->toMediaCollection('attachments');
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.duplicate', $quote))->assertRedirect();

    $copy = Quote::query()->whereKeyNot($quote->id)->sole();

    expect($copy->status)->toBe(QuoteStatus::Draft)
        ->and($copy->customer_id)->toBe($quote->customer_id)
        ->and($copy->notes)->toBe('Rush')
        ->and($copy->due_date->toDateString())->toBe('2026-12-01')
        ->and($copy->sent_at)->toBeNull()
        ->and($copy->emailed_at)->toBeNull()
        ->and($copy->lineItems)->toHaveCount(1)
        ->and($copy->lineItems->first()->description)->toBe('Item')
        ->and($copy->attachments())->toHaveCount(0)
        ->and($quote->fresh()->lineItems)->toHaveCount(1);
});

test('a user cannot duplicate another shop\'s quote', function () {
    $quote = draftQuoteWithLineItem(Shop::factory()->create());
    $user = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($user)->post(route('quotes.duplicate', $quote))->assertNotFound();
});

test('with emailing off, a quote cannot be emailed but can be marked sent without a reason', function () {
    Bus::fake();

    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $shop->update(['quote_emailing_enabled' => false]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.send', $quote))->assertSessionHasErrors('status');
    expect($quote->refresh()->status)->toBe(QuoteStatus::Draft);

    $this->actingAs($user)->post(route('quotes.skip-send', $quote))->assertSessionHasNoErrors();
    expect($quote->refresh()->status)->toBe(QuoteStatus::Sent)
        ->and($quote->send_skipped_reason)->toBe('Quote emailing is turned off for this shop');

    $this->actingAs($user)->post(route('quotes.resend', $quote))->assertSessionHasErrors('status');

    Bus::assertNotDispatched(GenerateAndSendQuotePdf::class);
});

test('emailing needs the plan feature even when the setting is on', function () {
    Bus::fake();

    $shop = Shop::factory()->create();
    $quote = draftQuoteWithLineItem($shop);
    $this->seed(PlanSeeder::class);
    $shop->update(['trial_ends_at' => now()->subDay(), 'plan_id' => DB::table('plans')->where('key', 'solo')->value('id')]);
    // A plan with quoting but no emailing (Solo includes it by default).
    DB::table('plan_feature')->where('plan_id', $shop->fresh()->plan_id)->where('feature', 'quote-emailing')->delete();
    $user = actingAsShopMember($shop);

    expect($shop->fresh()->quoteEmailingEnabled())->toBeFalse();

    $this->actingAs($user)->post(route('quotes.send', $quote))->assertSessionHasErrors('status');
    Bus::assertNotDispatched(GenerateAndSendQuotePdf::class);
});
