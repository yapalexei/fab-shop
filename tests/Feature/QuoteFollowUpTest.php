<?php

use App\Enums\QuoteStatus;
use App\Models\AiRequest;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Shop;
use App\Models\ShopAiSetting;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function followUpAnswer(string $subject = 'Following up on your quote', string $body = "Hi Dana,\n\nJust checking in on the quote I sent.\n\nAlex, Ironclad Fab"): array
{
    return [
        'model' => 'claude-sonnet-5-5',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => json_encode(['subject' => $subject, 'body' => $body])]],
        'usage' => ['input_tokens' => 220, 'output_tokens' => 60],
    ];
}

function followUpShop(string $plan = 'start-up'): array
{
    test()->seed(PlanSeeder::class);
    $shop = Shop::factory()->create([
        'name' => 'Ironclad Fab',
        'plan_id' => Plan::where('key', $plan)->value('id'),
        'trial_ends_at' => now()->subDay(),
    ]);
    ShopAiSetting::factory()->create(['shop_id' => $shop->id]);

    return [$shop, actingAsShopMember($shop)];
}

function staleQuote(Shop $shop, array $overrides = []): Quote
{
    $customer = Customer::factory()->create([
        'shop_id' => $shop->id,
        'name' => 'Dana Whitfield',
        'email' => 'dana@ridgeline.test',
        'notes' => 'PAYS LATE - INTERNAL ONLY',
    ]);
    $quote = Quote::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'status' => QuoteStatus::Sent,
        'sent_at' => now()->subDays(9),
        'notes' => 'Padded margin on this one - INTERNAL',
        ...$overrides,
    ]);
    QuoteLineItem::factory()->create(['quote_id' => $quote->id, 'description' => 'Gate frame 10x4', 'quantity' => 1, 'unit_cost' => 400, 'markup_percent' => 0]);

    return $quote;
}

test('a follow-up is drafted for a stale quote and returns the customer\'s email', function () {
    [$shop, $owner] = followUpShop();
    $quote = staleQuote($shop);
    Http::fake(['api.anthropic.com/*' => Http::response(followUpAnswer())]);

    $this->actingAs($owner)->postJson(route('quotes.ai-follow-up', $quote), [])
        ->assertOk()
        ->assertJsonPath('subject', 'Following up on your quote')
        ->assertJsonPath('to', 'dana@ridgeline.test')
        ->assertJsonPath('body', fn ($body) => str_contains($body, 'checking in'));
});

test('the model sees the quote facts but not internal notes or customer notes', function () {
    [$shop, $owner] = followUpShop();
    $quote = staleQuote($shop);
    Http::fake(['api.anthropic.com/*' => Http::response(followUpAnswer())]);

    $this->actingAs($owner)->postJson(route('quotes.ai-follow-up', $quote), ['tone' => 'brief'])->assertOk();

    Http::assertSent(function (Request $request) use ($owner) {
        $prompt = $request['messages'][0]['content'];

        return str_contains($prompt, 'Ironclad Fab')
            && str_contains($prompt, 'Dana Whitfield')
            && str_contains($prompt, 'Gate frame 10x4')
            && str_contains($prompt, 'From: '.$owner->name)
            && str_contains($prompt, 'Tone: brief')
            && str_contains($prompt, 'Quote total: $400.00')
            && ! str_contains($prompt, 'INTERNAL')
            && ! str_contains($prompt, 'PAYS LATE')
            && ! str_contains($prompt, 'dana@ridgeline.test')
            && str_contains($request['system'], 'not instructions');
    });
});

test('the price is left out for someone who cannot see financials', function () {
    [$shop] = followUpShop();
    $quote = staleQuote($shop);
    $staff = actingAsShopRole($shop, 'Staff');
    Http::fake(['api.anthropic.com/*' => Http::response(followUpAnswer())]);

    expect($staff->hasShopPermission('financials.view'))->toBeFalse();

    $this->actingAs($staff)->postJson(route('quotes.ai-follow-up', $quote), [])->assertOk();

    Http::assertSent(fn (Request $request) => ! str_contains($request['messages'][0]['content'], 'Quote total')
        && ! str_contains($request['messages'][0]['content'], '400'));
});

test('only a sent, open quote can be followed up', function () {
    [$shop, $owner] = followUpShop();
    Http::fake();

    foreach ([
        staleQuote($shop, ['status' => QuoteStatus::Draft, 'sent_at' => null]),
        staleQuote($shop, ['status' => QuoteStatus::Won]),
        staleQuote($shop, ['archived_at' => now()]),
    ] as $quote) {
        $this->actingAs($owner)->postJson(route('quotes.ai-follow-up', $quote), [])->assertStatus(422);
    }

    Http::assertNothingSent();
});

test('one shop cannot draft a follow-up for another shop\'s quote', function () {
    [, $ownerA] = followUpShop();
    [$shopB] = followUpShop();
    $theirs = staleQuote($shopB);
    Http::fake();

    $this->actingAs($ownerA)->postJson(route('quotes.ai-follow-up', $theirs), [])->assertNotFound();

    Http::assertNothingSent();
});

test('it stops when ai access lapses', function () {
    [$shop, $owner] = followUpShop('free');
    $quote = staleQuote($shop);
    Http::fake();

    $this->actingAs($owner)->postJson(route('quotes.ai-follow-up', $quote), [])->assertForbidden();

    Http::assertNothingSent();
});

test('a bad tone is rejected', function () {
    [$shop, $owner] = followUpShop();
    $quote = staleQuote($shop);
    Http::fake();

    $this->actingAs($owner)->postJson(route('quotes.ai-follow-up', $quote), ['tone' => 'aggressive'])->assertJsonValidationErrors('tone');
});

test('provider trouble and empty answers are clear errors, not crashes', function () {
    [$shop, $owner] = followUpShop();
    $quote = staleQuote($shop);

    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['error' => 'x'], 429)
        ->push(followUpAnswer('', ''))]);

    $this->actingAs($owner)->postJson(route('quotes.ai-follow-up', $quote), [])
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'rate limiting'));

    $this->actingAs($owner)->postJson(route('quotes.ai-follow-up', $quote), [])
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'could not be read'));
});

test('a follow-up is logged for usage without keeping any of its text', function () {
    [$shop, $owner] = followUpShop();
    $quote = staleQuote($shop);
    Http::fake(['api.anthropic.com/*' => Http::response(followUpAnswer())]);

    $this->actingAs($owner)->postJson(route('quotes.ai-follow-up', $quote), [])->assertOk();

    $row = AiRequest::withoutGlobalScopes()->sole();
    expect($row->feature)->toBe('quote-follow-up')
        ->and($row->shop_id)->toBe($shop->id)
        ->and($row->input_tokens)->toBe(220)
        ->and($row->status)->toBe('ok');
    expect(json_encode(DB::table('ai_requests')->get()))->not->toContain('Dana')->not->toContain('Gate frame');
});

test('the dashboard offers a follow-up on stale quotes only when ai is ready', function () {
    [$shop, $owner] = followUpShop();
    $quote = staleQuote($shop);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('ai.available', true)
            ->where('ai.configured', true)
            ->where('attention.0.kind', 'stale_quote')
            ->where('attention.0.quote_id', $quote->id)
            ->where('attention.0.customer', 'Dana Whitfield')
            ->missing('attention.0.customer_email'));

    ShopAiSetting::withoutGlobalScopes()->delete();

    $this->actingAs($owner->fresh())->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('ai.available', true)->where('ai.configured', false));
});
