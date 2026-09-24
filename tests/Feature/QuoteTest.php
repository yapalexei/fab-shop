<?php

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Shop;

test('a quote total, cost, and margin are computed from its line items', function () {
    $shop = Shop::factory()->create();
    $quote = Quote::factory()->for($shop)->create();

    // cost 100, +25% markup => total 125
    $quote->lineItems()->create([
        'type' => 'material', 'description' => 'Steel bar',
        'quantity' => 10, 'unit_cost' => 10, 'markup_percent' => 25,
    ]);

    // cost 200, +50% markup => total 300, but overridden to 250
    $quote->lineItems()->create([
        'type' => 'labor', 'description' => 'Welding',
        'quantity' => 4, 'unit_cost' => 50, 'markup_percent' => 50, 'override_total' => 250,
    ]);

    $quote->refresh();

    expect($quote->cost)->toBe(300.0)
        ->and($quote->total)->toBe(375.0)
        ->and($quote->margin)->toBe(75.0)
        ->and(round($quote->margin_percent, 2))->toBe(20.0);
});

test('a quote can be created with line items', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'notes' => 'Rush job',
        'line_items' => [
            ['type' => 'material', 'description' => 'Angle iron', 'quantity' => 5, 'unit_cost' => 20, 'markup_percent' => 30, 'override_total' => null],
        ],
    ])->assertRedirect();

    $quote = Quote::sole();

    expect($quote->customer_id)->toBe($customer->id)
        ->and($quote->shop_id)->toBe($shop->id)
        ->and($quote->lineItems)->toHaveCount(1)
        ->and($quote->total)->toBe(130.0);
});

test('a quote cannot be created for a customer belonging to another shop', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $foreignCustomer = Customer::factory()->for($otherShop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $foreignCustomer->id,
        'status' => 'draft',
        'line_items' => [
            ['type' => 'material', 'description' => 'X', 'quantity' => 1, 'unit_cost' => 1, 'markup_percent' => 0],
        ],
    ])->assertSessionHasErrors('customer_id');
});

test('a user cannot access another shop\'s quote', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $foreignQuote = Quote::factory()->for($otherShop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('quotes.edit', $foreignQuote))->assertNotFound();
});

test('updating a quote replaces its line items', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $quote = Quote::factory()->for($shop)->create(['customer_id' => $customer->id]);
    $quote->lineItems()->create([
        'type' => 'material', 'description' => 'Old item',
        'quantity' => 1, 'unit_cost' => 1, 'markup_percent' => 0,
    ]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->put(route('quotes.update', $quote), [
        'customer_id' => $customer->id,
        'line_items' => [
            ['type' => 'labor', 'description' => 'New item', 'quantity' => 2, 'unit_cost' => 30, 'markup_percent' => 10],
        ],
    ])->assertRedirect(route('quotes.edit', $quote));

    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->lineItems)->toHaveCount(1)
        ->and($quote->lineItems->first()->description)->toBe('New item');
});

test('a quote cannot have its status changed through the generic update endpoint', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $quote = Quote::factory()->for($shop)->create(['customer_id' => $customer->id]);
    $quote->lineItems()->create([
        'type' => 'material', 'description' => 'Item',
        'quantity' => 1, 'unit_cost' => 1, 'markup_percent' => 0,
    ]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->put(route('quotes.update', $quote), [
        'customer_id' => $customer->id,
        'status' => 'won',
        'line_items' => [
            ['type' => 'material', 'description' => 'Item', 'quantity' => 1, 'unit_cost' => 1, 'markup_percent' => 0],
        ],
    ])->assertRedirect(route('quotes.edit', $quote));

    expect($quote->refresh()->status)->toBe(QuoteStatus::Draft);
});

test('a quote requires at least one line item', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'line_items' => [],
    ])->assertSessionHasErrors('line_items');
});

test('an incomplete line item returns a field-specific, friendly error', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);

    $response = $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'line_items' => [
            ['type' => 'material', 'description' => '', 'quantity' => 1, 'unit_cost' => 0, 'markup_percent' => 0],
        ],
    ]);

    $response->assertSessionHasErrors(['line_items.0.description']);
    expect(session('errors')->get('line_items.0.description')[0])->toBe('The description field is required.');
});

test('the quote builder route is locked for a shop without the feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('quotes.index'))->assertForbidden();
});
