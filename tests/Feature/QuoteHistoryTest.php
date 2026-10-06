<?php

use App\Enums\Feature;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteHistoryEntry;
use App\Models\Shop;
use App\Models\ShopFeatureSubscription;
use App\Support\QuoteHistory;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Bus;

function historyShop(string $plan = 'pro'): Shop
{
    test()->seed(PlanSeeder::class);

    return Shop::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'plan_id' => Plan::where('key', $plan)->value('id'),
    ]);
}

function quoteFor(Shop $shop): Quote
{
    $customer = Customer::factory()->for($shop)->create(['name' => 'Acme']);
    $quote = Quote::factory()->for($shop)->create(['customer_id' => $customer->id, 'notes' => null]);
    $quote->lineItems()->create(['type' => 'material', 'description' => 'Plate', 'quantity' => 2, 'unit_cost' => 10, 'markup_percent' => 20, 'sort_order' => 0]);

    return $quote;
}

function updatePayload(Quote $quote, array $lines, ?string $notes = null): array
{
    return ['customer_id' => $quote->customer_id, 'notes' => $notes, 'line_items' => $lines];
}

function plate(array $overrides = []): array
{
    return ['type' => 'material', 'description' => 'Plate', 'quantity' => 2, 'unit_cost' => 10, 'markup_percent' => 20, ...$overrides];
}

test('a save on an entitled shop logs what changed and who changed it', function () {
    $shop = historyShop();
    $quote = quoteFor($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->put(route('quotes.update', $quote), updatePayload($quote, [plate(['quantity' => 3])], 'Rush job'))
        ->assertRedirect();

    $entry = QuoteHistoryEntry::query()->where('quote_id', $quote->id)->sole();
    $changes = collect($entry->changes);

    expect($entry->user_id)->toBe($user->id)
        ->and($changes->firstWhere('subject', 'Notes'))->toMatchArray(['type' => 'changed', 'from' => null, 'to' => 'Rush job'])
        ->and($changes->firstWhere('subject', 'Material line "Plate" - quantity'))->toMatchArray(['from' => '2', 'to' => '3'])
        ->and($changes)->toHaveCount(2);
});

test('adding and removing lines is logged, and a save with no changes logs nothing', function () {
    $shop = historyShop();
    $quote = quoteFor($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->put(route('quotes.update', $quote), updatePayload($quote, [plate()]));
    expect(QuoteHistoryEntry::query()->count())->toBe(0);

    $this->actingAs($user)->put(route('quotes.update', $quote), updatePayload($quote, [plate(['description' => 'Tube', 'quantity' => 1])]));

    $types = collect(QuoteHistoryEntry::query()->sole()->changes)->pluck('type');
    // "Plate" and "Tube" are both material lines, so they pair up as a rename + quantity edit.
    expect($types)->each->toBe('changed');

    $this->actingAs($user)->put(route('quotes.update', $quote), updatePayload($quote, [plate(['description' => 'Tube', 'quantity' => 1]), plate(['description' => 'Bolt', 'type' => 'labor'])]));
    expect(collect(QuoteHistoryEntry::query()->latest('id')->first()->changes)->pluck('type')->all())->toBe(['added']);
});

test('workflow events are logged', function () {
    Bus::fake();
    $shop = historyShop();
    $quote = quoteFor($shop);
    $quote->customer->update(['email' => 'a@example.com']);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.skip-send', $quote), ['reason' => 'Agreed verbally']);
    $this->actingAs($user)->post(route('quotes.mark-won', $quote));

    expect(QuoteHistoryEntry::query()->orderBy('id')->pluck('event')->all())->toBe(['send_skipped', 'won']);
});

test('a shop without the feature neither writes nor sees history', function () {
    $shop = historyShop('solo');
    $quote = quoteFor($shop);
    $user = actingAsShopMember($shop);

    expect(QuoteHistory::enabled())->toBeFalse();

    $this->actingAs($user)->put(route('quotes.update', $quote), updatePayload($quote, [plate(['quantity' => 9])]));
    expect(QuoteHistoryEntry::query()->withoutGlobalScopes()->count())->toBe(0);

    $this->actingAs($user)->get(route('quotes.edit', $quote))->assertInertia(fn ($page) => $page
        ->where('history.entries', null)
        ->where('history.price_cents', 1000));
});

test('the add-on subscription turns history on', function () {
    $shop = historyShop('solo');
    ShopFeatureSubscription::query()->forceCreate(['shop_id' => $shop->id, 'feature' => Feature::QuoteHistory->value, 'started_at' => now()]);
    $quote = quoteFor($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.mark-lost', $quote)); // not sent: refused, nothing logged
    $this->actingAs($user)->put(route('quotes.update', $quote), updatePayload($quote, [plate(['quantity' => 5])]));

    expect(QuoteHistoryEntry::query()->count())->toBe(1);
});

test('a shop that lost the feature still sees what was recorded, read-only', function () {
    $shop = historyShop('solo');
    $quote = quoteFor($shop);
    $user = actingAsShopMember($shop);
    QuoteHistoryEntry::query()->forceCreate(['shop_id' => $shop->id, 'quote_id' => $quote->id, 'user_id' => $user->id, 'event' => 'updated', 'summary' => 'Saved changes', 'changes' => []]);

    $this->actingAs($user)->get(route('quotes.edit', $quote))->assertInertia(fn ($page) => $page
        ->where('history.read_only', true)
        ->has('history.entries', 1));
});

test('quote history is isolated per shop', function () {
    $shopA = historyShop();
    $shopB = Shop::factory()->create();
    $quoteB = quoteFor($shopB);
    QuoteHistoryEntry::query()->forceCreate(['shop_id' => $shopB->id, 'quote_id' => $quoteB->id, 'event' => 'updated', 'summary' => 'Secret', 'changes' => []]);
    $this->actingAs(actingAsShopMember($shopA));

    expect(QuoteHistoryEntry::query()->count())->toBe(0);
});

test('Shop and Pro include quote history; Solo does not; add-on price defaults to $10', function () {
    $this->seed(PlanSeeder::class);

    expect(Plan::where('key', 'pro')->first()->hasFeature(Feature::QuoteHistory))->toBeTrue()
        ->and(Plan::where('key', 'start-up')->first()->hasFeature(Feature::QuoteHistory))->toBeTrue()
        ->and(Plan::where('key', 'solo')->first()->hasFeature(Feature::QuoteHistory))->toBeFalse()
        ->and(Feature::QuoteHistory->defaultPriceCents())->toBe(1000);
});
