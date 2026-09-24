<?php

use App\Enums\CuttableMaterial;
use App\Models\Customer;
use App\Models\MarkupDefault;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Shop;
use App\Models\Tool;

test('a tool can be added with the materials it is rated to cut', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('tools.store'), [
        'name' => '1/4in cobalt drill bit',
        'cuts' => ['carbon-steel', 'stainless-steel'],
        'cost' => 6.5,
        'unit' => 'each',
    ])->assertRedirect(route('tools.index'));

    $tool = Tool::sole();

    expect($tool->shop_id)->toBe($shop->id)
        ->and($tool->cuts->all())->toBe([CuttableMaterial::CarbonSteel, CuttableMaterial::StainlessSteel])
        ->and($tool->cutsLabel())->toBe('Carbon steel, Stainless steel')
        ->and((float) $tool->cost)->toBe(6.5);
});

test('the same tool can exist separately for steel and for aluminum', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    foreach (['carbon-steel', 'aluminum'] as $material) {
        $this->actingAs($user)->post(route('tools.store'), [
            'name' => '1/4in drill bit', 'cuts' => [$material], 'cost' => 4, 'unit' => 'each',
        ])->assertSessionHasNoErrors();
    }

    $this->actingAs($user)->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page
            ->has('tools', 2)
            ->where('tools.0.cuts', ['carbon-steel'])
            ->where('tools.1.cuts', ['aluminum']));
});

test('a tool must be rated for at least one known material', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('tools.store'), [
        'name' => 'Mystery bit', 'cuts' => [], 'cost' => 4, 'unit' => 'each',
    ])->assertSessionHasErrors('cuts');

    $this->actingAs($user)->post(route('tools.store'), [
        'name' => 'Mystery bit', 'cuts' => ['unobtainium'], 'cost' => 4, 'unit' => 'each',
    ])->assertSessionHasErrors('cuts.0');

    expect(Tool::count())->toBe(0);
});

test('a tool can be edited', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $tool = Tool::factory()->for($shop)->create(['cuts' => ['aluminum']]);

    $this->actingAs($user)->put(route('tools.update', $tool), [
        'name' => '1/2in hole saw', 'cuts' => ['aluminum', 'plastic'], 'cost' => 18, 'unit' => 'each',
    ])->assertRedirect(route('tools.index'));

    expect($tool->fresh()->cutsLabel())->toBe('Aluminum, Plastic');
});

test('a user cannot see or change another shop\'s tools', function () {
    $shop = Shop::factory()->create();
    $foreign = Tool::factory()->for(Shop::factory()->create())->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('tools.index'))->assertInertia(fn ($page) => $page->has('tools', 0));
    $this->actingAs($user)->get(route('tools.edit', $foreign))->assertNotFound();
    $this->actingAs($user)->put(route('tools.update', $foreign), [
        'name' => 'x', 'cuts' => ['wood'], 'cost' => 1, 'unit' => 'each',
    ])->assertNotFound();
    $this->actingAs($user)->delete(route('tools.destroy', $foreign))->assertNotFound();
});

test('staff can see tooling but not change it by default', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $tool = Tool::factory()->for($shop)->create();

    $this->actingAs($staff)->get(route('tools.index'))->assertOk();
    $this->actingAs($staff)->post(route('tools.store'), [
        'name' => 'x', 'cuts' => ['wood'], 'cost' => 1, 'unit' => 'each',
    ])->assertForbidden();
    $this->actingAs($staff)->delete(route('tools.destroy', $tool))->assertForbidden();
});

test('tooling is locked for a shop without the quote builder', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('tools.index'))->assertForbidden();
});

test('a quote can carry a tooling line that references a tool', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $customer = Customer::factory()->for($shop)->create();
    $tool = Tool::factory()->for($shop)->create(['name' => '1/4in drill bit', 'cuts' => ['aluminum'], 'cost' => 4]);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'line_items' => [[
            'type' => 'tooling', 'description' => $tool->name,
            'quantity' => 3, 'unit_cost' => 4, 'markup_percent' => 10,
            'tool_id' => $tool->id,
        ]],
    ])->assertSessionHasNoErrors();

    $line = Quote::sole()->lineItems->first();
    expect($line->tool_id)->toBe($tool->id)
        ->and(round($line->total, 2))->toBe(13.2);
});

test('a quote cannot reference another shop\'s tool', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $customer = Customer::factory()->for($shop)->create();
    $foreign = Tool::factory()->for(Shop::factory()->create())->create();

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'line_items' => [[
            'type' => 'tooling', 'description' => 'x', 'quantity' => 1,
            'unit_cost' => 1, 'markup_percent' => 0, 'tool_id' => $foreign->id,
        ]],
    ])->assertSessionHasErrors('line_items.0.tool_id');
});

test('deleting a tool keeps past quote lines intact', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $tool = Tool::factory()->for($shop)->create(['name' => '1/2in hole saw']);
    $quote = Quote::factory()->for($shop)->create(['customer_id' => Customer::factory()->for($shop)->create()->id]);
    $quote->lineItems()->create([
        'type' => 'tooling', 'description' => '1/2in hole saw', 'quantity' => 1,
        'unit_cost' => 18, 'markup_percent' => 0, 'tool_id' => $tool->id,
    ]);

    $this->actingAs($user)->delete(route('tools.destroy', $tool))->assertRedirect(route('tools.index'));

    $line = $quote->lineItems()->first();
    expect($line->tool_id)->toBeNull()
        ->and($line->description)->toBe('1/2in hole saw')
        ->and((float) $line->unit_cost)->toBe(18.0);
});

test('the quote builder receives the tooling catalog and a tooling markup default', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    Tool::factory()->for($shop)->create(['name' => '1/4in drill bit', 'cuts' => ['carbon-steel', 'stainless-steel'], 'cost' => 6.5, 'unit' => 'each']);
    (new MarkupDefault)->forceFill(['shop_id' => $shop->id, 'line_item_type' => 'tooling', 'default_markup_percent' => 30])->save();

    $this->actingAs($user)->get(route('quotes.create'))
        ->assertInertia(fn ($page) => $page
            ->where('tools.0.name', '1/4in drill bit')
            ->where('tools.0.cuts', 'Carbon steel, Stainless steel')
            ->where('markupDefaults.tooling', 30));
});
