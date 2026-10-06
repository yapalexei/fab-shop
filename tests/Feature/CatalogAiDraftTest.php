<?php

use App\Models\AiRequest;
use App\Models\BusinessPartner;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopAiSetting;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

const PAGE_TEXT = 'Cobalt Steel Jobber-Length Drill Bit, 1/4" diameter. Item 2985A21. For hardened steel and stainless. $6.50 each.';

/** What the provider returns: the structured answer as a JSON string. */
function catalogAnswer(array $fields): array
{
    return [
        'model' => 'claude-sonnet-5-5',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => json_encode($fields)]],
        'usage' => ['input_tokens' => 300, 'output_tokens' => 60],
    ];
}

function toolFields(array $overrides = []): array
{
    return array_merge([
        'name' => '1/4in cobalt drill bit', 'cost' => 6.5, 'unit' => 'each',
        'part_number' => '2985A21', 'cuts' => [], 'notes' => 'Cobalt steel, jobber length.',
    ], $overrides);
}

/** A Shop-plan shop with a saved key, and its owner. */
function catalogAiShop(): array
{
    test()->seed(PlanSeeder::class);
    $shop = Shop::factory()->create([
        'plan_id' => Plan::where('key', 'start-up')->value('id'),
        'trial_ends_at' => now()->subDay(),
    ]);
    ShopAiSetting::factory()->create(['shop_id' => $shop->id]);

    return [$shop, actingAsShopMember($shop)];
}

function typeId(Shop $shop, string $name): int
{
    return MaterialType::withoutGlobalScopes()->where('shop_id', $shop->id)->where('name', $name)->value('id');
}

test('a tool draft returns cleaned fields, the owner\'s own link, and checks to make', function () {
    [$shop, $owner] = catalogAiShop();
    $steel = typeId($shop, 'Carbon steel');
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(toolFields(['cuts' => [$steel]])))]);

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'tools'), [
        'text' => PAGE_TEXT, 'source_url' => 'https://www.mcmaster.com/2985A21/',
    ])->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('fields.name', '1/4in cobalt drill bit')
        ->assertJsonPath('fields.cost', 6.5)
        ->assertJsonPath('fields.part_number', '2985A21')
        ->assertJsonPath('fields.cuts', [$steel])
        ->assertJsonPath('fields.source_url', 'https://www.mcmaster.com/2985A21/')
        ->assertJsonPath('checks.0', fn ($c) => str_contains($c, 'Check the price'));

    // The model is never shown the link, and is told the page is untrusted data.
    Http::assertSent(fn (Request $r) => ! str_contains(json_encode($r->data()), 'mcmaster.com')
        && str_contains($r['messages'][0]['content'], '<supplier_page>')
        && str_contains($r['system'], 'untrusted data'));
});

test('material types the model invents or takes from another shop are dropped', function () {
    [$shop, $owner] = catalogAiShop();
    $foreign = typeId(Shop::factory()->create(), 'Aluminum');
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(toolFields(['cuts' => [$foreign, 999999]])))]);

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'tools'), ['text' => PAGE_TEXT])
        ->assertOk()
        ->assertJsonPath('fields.cuts', [])
        ->assertJsonPath('checks', fn ($checks) => collect($checks)->contains(fn ($c) => str_contains($c, 'which materials')));

    // Only this shop's types are offered to the provider.
    Http::assertSent(fn (Request $r) => str_contains($r['messages'][0]['content'], 'Carbon steel'));
});

test('a missing or nonsense price is reported, not invented', function () {
    [, $owner] = catalogAiShop();
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(toolFields(['cost' => null])))]);

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'tools'), ['text' => PAGE_TEXT])
        ->assertOk()
        ->assertJsonPath('fields.cost', null)
        ->assertJsonPath('checks', fn ($checks) => collect($checks)->contains(fn ($c) => str_contains($c, 'No price found')));

    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(toolFields(['cost' => -5])))]);

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'tools'), ['text' => PAGE_TEXT])
        ->assertJsonPath('fields.cost', null);
});

test('text that is not a single product comes back as not found', function () {
    [, $owner] = catalogAiShop();
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(toolFields(['name' => null, 'cost' => null])))]);

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'tools'), ['text' => PAGE_TEXT])
        ->assertOk()->assertJsonPath('found', false)->assertJsonPath('checks', []);
});

test('a material draft picks one of the shop\'s own types and never returns a price', function () {
    [$shop, $owner] = catalogAiShop();
    $steel = typeId($shop, 'Carbon steel');
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer([
        'name' => '1/4in A36 steel plate', 'unit' => 'sheet', 'material_type_id' => $steel, 'notes' => 'Hot rolled.',
    ]))]);

    $response = $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'materials'), ['text' => '1/4" A36 hot-rolled steel plate, 48 x 96, $210.'])
        ->assertOk()
        ->assertJsonPath('fields.material_type_id', $steel)
        ->assertJsonPath('fields.unit', 'sheet')
        ->assertJsonMissingPath('fields.cost')
        ->assertJsonMissingPath('fields.source_url');

    expect(array_keys($response->json('fields')))->toBe(['name', 'unit', 'material_type_id', 'notes']);
});

test('over-long text and a bad link are refused before anything is sent', function () {
    [, $owner] = catalogAiShop();
    Http::fake();

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'tools'), ['text' => 'short'])->assertJsonValidationErrors('text');
    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'tools'), ['text' => str_repeat('a', config('ai.max_message_length') + 1)])->assertJsonValidationErrors('text');
    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'tools'), ['text' => PAGE_TEXT, 'source_url' => 'javascript:alert(1)'])->assertJsonValidationErrors('source_url');

    Http::assertNothingSent();
});

test('an unknown kind 404s', function () {
    [, $owner] = catalogAiShop();

    $this->actingAs($owner)->postJson('/catalog/customers/ai-draft', ['text' => PAGE_TEXT])->assertNotFound();
});

test('a draft is logged for usage without keeping the pasted text', function () {
    [$shop, $owner] = catalogAiShop();
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(toolFields()))]);

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'tools'), ['text' => PAGE_TEXT])->assertOk();

    $row = AiRequest::withoutGlobalScopes()->sole();
    expect($row->shop_id)->toBe($shop->id)->and($row->feature)->toBe('catalog-intake')->and($row->status)->toBe('ok');
    expect(json_encode(DB::table('ai_requests')->get()))->not->toContain('2985A21');
});

test('a provider failure is a clear message', function () {
    [, $owner] = catalogAiShop();
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'nope'], 429)]);

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'tools'), ['text' => PAGE_TEXT])
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'rate limiting'));
});

// --- access ------------------------------------------------------------------

test('after the trial on free, drafting is refused and nothing is sent', function () {
    $this->seed(PlanSeeder::class);
    $shop = Shop::factory()->create(['plan_id' => Plan::where('key', 'free')->value('id'), 'trial_ends_at' => now()->subDay()]);
    ShopAiSetting::factory()->create(['shop_id' => $shop->id]);
    Http::fake();

    $this->actingAs(actingAsShopMember($shop))->postJson(route('catalog.ai-draft', 'tools'), ['text' => PAGE_TEXT])->assertForbidden();

    Http::assertNothingSent();
});

test('drafting a tool needs tooling.create and a material needs materials.create', function () {
    [$shop] = catalogAiShop();
    $staff = actingAsShopRole($shop, 'Staff');
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(toolFields()))]);

    // Staff can't create either by default.
    $this->actingAs($staff)->postJson(route('catalog.ai-draft', 'tools'), ['text' => PAGE_TEXT])->assertForbidden();
    $this->actingAs($staff)->postJson(route('catalog.ai-draft', 'materials'), ['text' => PAGE_TEXT])->assertForbidden();

    // Granting one doesn't grant the other.
    DB::table('shop_user')->where(['shop_id' => $shop->id, 'user_id' => $staff->id])
        ->update(['permission_overrides' => json_encode(['tooling.create' => true])]);

    $this->actingAs($staff->fresh())->postJson(route('catalog.ai-draft', 'tools'), ['text' => PAGE_TEXT])->assertOk();
    $this->actingAs($staff->fresh())->postJson(route('catalog.ai-draft', 'materials'), ['text' => PAGE_TEXT])->assertForbidden();
});

test('the create pages are told whether AI is available and configured', function () {
    [$shop, $owner] = catalogAiShop();

    foreach (['tools.create', 'materials.create'] as $route) {
        $this->actingAs($owner)->get(route($route))
            ->assertInertia(fn ($page) => $page->where('ai.available', true)->where('ai.configured', true));
    }

    ShopAiSetting::withoutGlobalScopes()->delete();

    $this->actingAs($owner->fresh())->get(route('tools.create'))
        ->assertInertia(fn ($page) => $page->where('ai.available', true)->where('ai.configured', false));
});

// --- supplier prices (a row on a business partner's price table) ----------------------

function priceFields(array $overrides = []): array
{
    return array_merge([
        'name' => '1/4in A36 steel plate', 'material_id' => null, 'dimension' => '4 x 8 ft',
        'price' => 1210.5, 'part_number' => '8910K12',
    ], $overrides);
}

test('a price draft matches the shop\'s own material and returns the owner\'s link', function () {
    [$shop, $owner] = catalogAiShop();
    $plate = Material::factory()->create(['shop_id' => $shop->id, 'name' => '1/4in A36 plate']);
    $foreign = Material::factory()->create(['shop_id' => Shop::factory()->create()->id, 'name' => 'Rival Plate']);
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(priceFields(['material_id' => $plate->id])))]);

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'supplier-prices'), [
        'text' => PAGE_TEXT, 'source_url' => 'https://www.mcmaster.com/8910K12/',
    ])->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('fields.material_id', $plate->id)
        ->assertJsonPath('fields.dimension', '4 x 8 ft')
        ->assertJsonPath('fields.price', 1210.5)
        ->assertJsonPath('fields.part_number', '8910K12')
        ->assertJsonPath('fields.source_url', 'https://www.mcmaster.com/8910K12/')
        ->assertJsonPath('checks', fn ($checks) => count($checks) === 1 && str_contains($checks[0], 'Check the price'));

    // Only this shop's materials (names and ids) go to the provider, and never the link.
    Http::assertSent(fn (Request $r) => str_contains($r['messages'][0]['content'], '1/4in A36 plate')
        && ! str_contains($r['messages'][0]['content'], 'Rival Plate')
        && ! str_contains(json_encode($r->data()), 'mcmaster.com'));
});

test('a material id the model invents or takes from another shop is dropped, with a pointer to add it', function () {
    [, $owner] = catalogAiShop();
    $foreign = Material::factory()->create(['shop_id' => Shop::factory()->create()->id]);
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(priceFields(['material_id' => $foreign->id])))]);

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'supplier-prices'), ['text' => PAGE_TEXT])
        ->assertOk()
        ->assertJsonPath('fields.material_id', null)
        ->assertJsonPath('checks', fn ($checks) => collect($checks)->contains(fn ($c) => str_contains($c, 'add it under Materials')));
});

test('a missing price on a price draft is reported, not invented', function () {
    [, $owner] = catalogAiShop();
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(priceFields(['price' => null])))]);

    $this->actingAs($owner)->postJson(route('catalog.ai-draft', 'supplier-prices'), ['text' => PAGE_TEXT])
        ->assertOk()
        ->assertJsonPath('fields.price', null)
        ->assertJsonPath('checks', fn ($checks) => collect($checks)->contains(fn ($c) => str_contains($c, 'No price found')));
});

test('drafting a supplier price needs a business-partners permission', function () {
    [$shop] = catalogAiShop();
    $staff = actingAsShopRole($shop, 'Staff');
    Http::fake(['api.anthropic.com/*' => Http::response(catalogAnswer(priceFields()))]);

    $this->actingAs($staff)->postJson(route('catalog.ai-draft', 'supplier-prices'), ['text' => PAGE_TEXT])->assertForbidden();

    DB::table('shop_user')->where(['shop_id' => $shop->id, 'user_id' => $staff->id])
        ->update(['permission_overrides' => json_encode(['business-partners.update' => true])]);

    $this->actingAs($staff->fresh())->postJson(route('catalog.ai-draft', 'supplier-prices'), ['text' => PAGE_TEXT])->assertOk();
});

test('the supplier pages are told whether AI is available and configured', function () {
    [$shop, $owner] = catalogAiShop();
    $partner = BusinessPartner::factory()->for($shop)->create();

    foreach ([route('business-partners.create'), route('business-partners.edit', $partner)] as $url) {
        $this->actingAs($owner)->get($url)
            ->assertInertia(fn ($page) => $page->where('ai.available', true)->where('ai.configured', true));
    }
});
