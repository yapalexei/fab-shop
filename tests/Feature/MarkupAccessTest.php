<?php

use App\Enums\LineItemType;
use App\Models\Customer;
use App\Models\MarkupDefault;
use App\Models\Quote;
use App\Models\Shop;
use App\Support\ShopPermissions;

function markupLine(array $overrides = []): array
{
    return [
        'type' => 'material',
        'description' => 'Steel plate',
        'quantity' => 2,
        'unit_cost' => 100,
        'markup_percent' => 25,
        ...$overrides,
    ];
}

function setMarkupDefault(Shop $shop, LineItemType $type, float $percent): void
{
    (new MarkupDefault)->forceFill(['shop_id' => $shop->id, 'line_item_type' => $type->value, 'default_markup_percent' => $percent])->save();
}

test('only the owner has markup access by default', function () {
    expect(ShopPermissions::defaultsFor('Admin'))->not->toContain('markup.view', 'markup.update')
        ->and(ShopPermissions::defaultsFor('Staff'))->not->toContain('markup.view', 'markup.update');

    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);
    $admin = actingAsShopMember($shop, 'Admin');
    $staff = actingAsShopMember($shop, 'Staff');

    expect($owner->hasShopPermission('markup.update'))->toBeTrue()
        ->and($admin->hasShopPermission('markup.view'))->toBeFalse()
        ->and($staff->hasShopPermission('markup.view'))->toBeFalse();

    $this->actingAs($admin)->get(route('markup-defaults.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('markup-defaults.index'))->assertForbidden();
    $this->actingAs($owner)->get(route('markup-defaults.index'))->assertOk();
});

test('the owner can award markup access to a member', function () {
    $shop = Shop::factory()->create();
    actingAsShopMember($shop);
    $staff = actingAsShopMember($shop, 'Staff');

    $shop->members()->updateExistingPivot($staff->id, ['permission_overrides' => ['markup.view' => true]]);

    $this->actingAs($staff->fresh())->get(route('markup-defaults.index'))->assertOk();
    $this->actingAs($staff->fresh())->patch(route('markup-defaults.update'), [
        'defaults' => [['line_item_type' => 'material', 'default_markup_percent' => 99]],
    ])->assertForbidden();

    $shop->members()->updateExistingPivot($staff->id, ['permission_overrides' => ['markup.update' => true]]);

    $this->actingAs($staff->fresh())->patch(route('markup-defaults.update'), [
        'defaults' => [['line_item_type' => 'material', 'default_markup_percent' => 40]],
    ])->assertRedirect(route('markup-defaults.index'));
});

test('someone without markup.update cannot set markup on a new quote - the shop default applies', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $staff = actingAsShopMember($shop, 'Staff');
    setMarkupDefault($shop, LineItemType::Material, 30);

    $this->actingAs($staff)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'line_items' => [markupLine(['markup_percent' => 500])],
    ])->assertSessionHasNoErrors();

    expect((float) Quote::sole()->lineItems()->sole()->markup_percent)->toBe(30.0);
});

test('someone without markup.update keeps a line\'s markup when editing it', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $owner = actingAsShopMember($shop);
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($owner)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'line_items' => [markupLine(['markup_percent' => 45])],
    ]);
    $quote = Quote::sole();
    $line = $quote->lineItems()->sole();

    // Edits the quantity and tries to zero the markup (or sends none at all).
    $this->actingAs($staff)->put(route('quotes.update', $quote), [
        'customer_id' => $customer->id,
        'line_items' => [markupLine(['id' => $line->id, 'quantity' => 5, 'markup_percent' => 0])],
    ])->assertSessionHasNoErrors();

    $this->actingAs($staff)->put(route('quotes.update', $quote), [
        'customer_id' => $customer->id,
        'line_items' => [['type' => 'material', 'description' => 'Steel plate', 'quantity' => 6, 'unit_cost' => 100, 'id' => $quote->fresh()->lineItems()->sole()->id]],
    ])->assertSessionHasNoErrors();

    $line = $quote->fresh()->lineItems()->sole();
    expect((float) $line->markup_percent)->toBe(45.0)
        ->and((float) $line->quantity)->toBe(6.0);
});

test('someone without markup.update gets the default when they change a line\'s type', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $owner = actingAsShopMember($shop);
    $staff = actingAsShopMember($shop, 'Staff');
    setMarkupDefault($shop, LineItemType::Labor, 12);

    $this->actingAs($owner)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'line_items' => [markupLine(['markup_percent' => 45])],
    ]);
    $quote = Quote::sole();

    $this->actingAs($staff)->put(route('quotes.update', $quote), [
        'customer_id' => $customer->id,
        'line_items' => [markupLine(['id' => $quote->lineItems()->sole()->id, 'type' => 'labor'])],
    ])->assertSessionHasNoErrors();

    expect((float) $quote->fresh()->lineItems()->sole()->markup_percent)->toBe(12.0);
});

test('a line id from another quote cannot be used to borrow its markup', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $owner = actingAsShopMember($shop);
    $staff = actingAsShopMember($shop, 'Staff');
    setMarkupDefault($shop, LineItemType::Material, 10);

    $this->actingAs($owner)->post(route('quotes.store'), ['customer_id' => $customer->id, 'line_items' => [markupLine(['markup_percent' => 90])]]);
    $this->actingAs($owner)->post(route('quotes.store'), ['customer_id' => $customer->id, 'line_items' => [markupLine(['markup_percent' => 90])]]);
    [$first, $second] = Quote::orderBy('id')->get();

    $this->actingAs($staff)->put(route('quotes.update', $second), [
        'customer_id' => $customer->id,
        'line_items' => [markupLine(['id' => $first->lineItems()->sole()->id])],
    ]);

    expect((float) $first->fresh()->lineItems()->sole()->markup_percent)->toBe(90.0)
        ->and((float) $second->fresh()->lineItems()->sole()->markup_percent)->toBe(10.0);
});

test('someone with markup.update still sets markup per line', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'line_items' => [markupLine(['markup_percent' => 22])],
    ])->assertSessionHasNoErrors();

    expect((float) Quote::sole()->lineItems()->sole()->markup_percent)->toBe(22.0);

    // ...and the owner must still supply it.
    $this->actingAs($owner)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'line_items' => [markupLine(['markup_percent' => null])],
    ])->assertSessionHasErrors('line_items.0.markup_percent');
});

test('quote history hides markup from someone who cannot see it', function () {
    $changes = [
        ['type' => 'changed', 'subject' => 'Material line "Plate" - markup %', 'from' => '20', 'to' => '35'],
        ['type' => 'changed', 'subject' => 'Material line "Plate" - quantity', 'from' => '1', 'to' => '2'],
        ['type' => 'added', 'subject' => 'Labor line "Weld"', 'to' => '2 x $50.00 + 25% markup, price override $140.00'],
        ['type' => 'removed', 'subject' => 'Labor line "Cut"', 'from' => '1 x $10.00 + 8.5% markup'],
    ];

    expect(App\Support\QuoteHistory::withoutMarkup($changes))->toBe([
        ['type' => 'changed', 'subject' => 'Material line "Plate" - quantity', 'from' => '1', 'to' => '2'],
        ['type' => 'added', 'subject' => 'Labor line "Weld"', 'to' => '2 x $50.00, price override $140.00'],
        ['type' => 'removed', 'subject' => 'Labor line "Cut"', 'from' => '1 x $10.00'],
    ]);
});
