<?php

use App\Models\Customer;
use App\Models\MarkupDefault;
use App\Models\MaterialType;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Shop;
use App\Models\Tool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Ids of the named material types in $shop.
 *
 * @return array<int, int>
 */
function typeIds(Shop $shop, string ...$names): array
{
    return collect($names)
        ->map(fn (string $name) => MaterialType::withoutGlobalScopes()->where('shop_id', $shop->id)->where('name', $name)->value('id'))
        ->all();
}

test('a tool can be added with the material types it is rated to cut', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('tools.store'), [
        'name' => '1/4in cobalt drill bit',
        'cuts' => typeIds($shop, 'Carbon steel', 'Stainless steel'),
        'cost' => 6.5,
        'unit' => 'each',
    ])->assertRedirect(route('tools.index'));

    $tool = Tool::sole();

    expect($tool->shop_id)->toBe($shop->id)
        ->and($tool->cutsLabel())->toBe('Carbon steel, Stainless steel')
        ->and((float) $tool->cost)->toBe(6.5);
});

test('the same tool can exist separately for steel and for aluminum', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    foreach (['Carbon steel', 'Aluminum'] as $type) {
        $this->actingAs($user)->post(route('tools.store'), [
            'name' => '1/4in drill bit', 'cuts' => typeIds($shop, $type), 'cost' => 4, 'unit' => 'each',
        ])->assertSessionHasNoErrors();
    }

    $this->actingAs($user)->get(route('tools.index'))
        ->assertInertia(fn ($page) => $page
            ->has('tools', 2)
            ->where('tools.0.cuts', typeIds($shop, 'Carbon steel'))
            ->where('tools.1.cuts', typeIds($shop, 'Aluminum')));
});

test('a tool can be rated for a shop\'s custom material type', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $this->actingAs($user)->post(route('material-types.store'), ['name' => 'Chromoly (4130)']);

    $this->actingAs($user)->post(route('tools.store'), [
        'name' => 'Chromoly notcher', 'cuts' => typeIds($shop, 'Chromoly (4130)'), 'cost' => 30, 'unit' => 'each',
    ])->assertSessionHasNoErrors();

    expect(Tool::sole()->cutsLabel())->toBe('Chromoly (4130)');
});

test('a tool must be rated for at least one of the shop\'s own material types', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('tools.store'), [
        'name' => 'Mystery bit', 'cuts' => [], 'cost' => 4, 'unit' => 'each',
    ])->assertSessionHasErrors('cuts');

    $this->actingAs($user)->post(route('tools.store'), [
        'name' => 'Mystery bit', 'cuts' => typeIds($otherShop, 'Aluminum'), 'cost' => 4, 'unit' => 'each',
    ])->assertSessionHasErrors('cuts.0');

    expect(Tool::count())->toBe(0);
});

test('a tool\'s ratings can be edited', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $tool = Tool::factory()->for($shop)->cutting('Aluminum')->create();

    $this->actingAs($user)->put(route('tools.update', $tool), [
        'name' => '1/2in hole saw', 'cuts' => typeIds($shop, 'Aluminum', 'Plastic'), 'cost' => 18, 'unit' => 'each',
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
        'name' => 'x', 'cuts' => typeIds($shop, 'Wood'), 'cost' => 1, 'unit' => 'each',
    ])->assertNotFound();
    $this->actingAs($user)->delete(route('tools.destroy', $foreign))->assertNotFound();
});

test('staff can see tooling but not change it by default', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $tool = Tool::factory()->for($shop)->create();

    $this->actingAs($staff)->get(route('tools.index'))->assertOk();
    $this->actingAs($staff)->post(route('tools.store'), [
        'name' => 'x', 'cuts' => typeIds($shop, 'Wood'), 'cost' => 1, 'unit' => 'each',
    ])->assertForbidden();
    $this->actingAs($staff)->delete(route('tools.destroy', $tool))->assertForbidden();
});

test('tooling is read-only for a shop without the quote builder', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('tools.index'))->assertOk();
    $this->actingAs($user)->post(route('tools.store'), [])->assertForbidden();
});

test('a quote can carry a tooling line that references a tool', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $customer = Customer::factory()->for($shop)->create();
    $tool = Tool::factory()->for($shop)->cutting('Aluminum')->create(['name' => '1/4in drill bit', 'cost' => 4]);

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
    $tool = Tool::factory()->for($shop)->cutting('Aluminum')->create(['name' => '1/2in hole saw']);
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

test('the quote builder receives tooling with its ratings and a tooling markup default', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    Tool::factory()->for($shop)->cutting('Carbon steel', 'Stainless steel')->create(['name' => '1/4in drill bit', 'cost' => 6.5]);
    (new MarkupDefault)->forceFill(['shop_id' => $shop->id, 'line_item_type' => 'tooling', 'default_markup_percent' => 30])->save();

    $this->actingAs($user)->get(route('quotes.create'))
        ->assertInertia(fn ($page) => $page
            ->where('tools.0.name', '1/4in drill bit')
            ->where('tools.0.cuts', 'Carbon steel, Stainless steel')
            ->where('tools.0.cut_type_ids', fn ($ids) => collect($ids)->sort()->values()->all() === collect(typeIds($shop, 'Carbon steel', 'Stainless steel'))->sort()->values()->all())
            ->where('markupDefaults.tooling', 30));
});

test('a tool can carry a photo and a 3D file, replaced and removed on update', function () {
    Storage::fake('public');
    $user = actingAsShopMember(Shop::factory()->create());
    $cuts = MaterialType::pluck('id')->take(1)->all();

    $this->actingAs($user)->post(route('tools.store'), [
        'name' => '1/4 bit', 'cuts' => $cuts, 'cost' => 5, 'unit' => 'hole',
        'image' => UploadedFile::fake()->image('bit.png'),
        'model' => UploadedFile::fake()->create('bit.stl', 100),
    ])->assertSessionHasNoErrors();

    $tool = Tool::sole();
    expect($tool->imageUrl())->not->toBeNull();
    $this->actingAs($user)->get($tool->imageUrl())->assertOk();
    $this->actingAs($user)->get(route('tools.model', $tool))->assertOk();

    $this->actingAs($user)->post(route('tools.update', $tool), [
        '_method' => 'put', 'name' => '1/4 bit', 'cuts' => $cuts, 'cost' => 5, 'unit' => 'hole',
        'model' => UploadedFile::fake()->create('new.step', 100),
    ])->assertSessionHasNoErrors();

    expect(Tool::sole()->getFirstMedia('model')->file_name)->toBe('new.step')
        ->and(Tool::sole()->imageUrl())->not->toBeNull();

    $this->actingAs($user)->put(route('tools.update', $tool), [
        'name' => '1/4 bit', 'cuts' => $cuts, 'cost' => 5, 'unit' => 'hole',
        'remove_image' => true, 'remove_model' => true,
    ])->assertSessionHasNoErrors();

    expect(Tool::sole()->imageUrl())->toBeNull()
        ->and(Tool::sole()->getMedia('model'))->toHaveCount(0);
});

test('tool files are validated and never served across shops', function () {
    Storage::fake('public');
    $user = actingAsShopMember(Shop::factory()->create());
    $cuts = MaterialType::pluck('id')->take(1)->all();
    $base = ['name' => 'Bit', 'cuts' => $cuts, 'cost' => 5, 'unit' => 'hole'];

    $this->actingAs($user)->post(route('tools.store'), $base + [
        'model' => UploadedFile::fake()->create('evil.html', 10),
    ])->assertSessionHasErrors('model');
    $this->actingAs($user)->post(route('tools.store'), $base + [
        'image' => UploadedFile::fake()->create('bit.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('image');

    $this->actingAs($user)->post(route('tools.store'), $base + [
        'model' => UploadedFile::fake()->create('bit.stl', 10),
    ]);
    $tool = Tool::sole();

    $other = actingAsShopMember(Shop::factory()->create());
    $this->actingAs($other)->get(route('tools.model', $tool))->assertNotFound();
});
