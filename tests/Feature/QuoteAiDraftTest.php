<?php

use App\Models\AiRequest;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopAiSetting;
use App\Models\Tool;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** What the provider returns: the structured answer as a JSON string. */
function aiAnswer(array $lines, array $questions = []): array
{
    return [
        'model' => 'claude-sonnet-5-5',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => json_encode(['lines' => $lines, 'questions' => $questions])]],
        'usage' => ['input_tokens' => 400, 'output_tokens' => 90],
    ];
}

function aiLine(array $overrides = []): array
{
    return array_merge([
        'type' => 'material',
        'description' => '1/4in A36 plate',
        'quantity' => 2,
        'quantity_is_stated' => true,
        'catalog_id' => null,
        'confidence' => 0.95,
        'reason' => 'Customer asked for plate.',
    ], $overrides);
}

/** A Start-up shop with a saved key, and its owner. */
function aiShop(): array
{
    test()->seed(PlanSeeder::class);
    $shop = Shop::factory()->create([
        'plan_id' => Plan::where('key', 'start-up')->value('id'),
        'trial_ends_at' => now()->subDay(),
    ]);
    ShopAiSetting::factory()->create(['shop_id' => $shop->id]);

    return [$shop, actingAsShopMember($shop)];
}

const REQUEST_TEXT = 'Hi, we need two sheets of 1/4in A36 plate cut to 24x36, welded into a frame, by Friday.';

test('a draft returns cleaned lines, matched to the shop\'s own catalog', function () {
    [$shop, $owner] = aiShop();
    $plate = Material::factory()->create(['shop_id' => $shop->id, 'name' => '1/4in A36 plate']);
    $service = Service::factory()->create(['shop_id' => $shop->id, 'name' => 'MIG welding']);
    Http::fake(['api.anthropic.com/*' => Http::response(aiAnswer([
        aiLine(['catalog_id' => $plate->id]),
        aiLine(['type' => 'labor', 'description' => 'Weld frame', 'catalog_id' => $service->id, 'quantity_is_stated' => false, 'confidence' => 0.5]),
    ], ['What finish do they want?']))]);

    $response = $this->actingAs($owner)->postJson(route('quotes.ai-draft'), ['message' => REQUEST_TEXT])
        ->assertOk()
        ->assertJsonPath('lines.0.catalog_id', $plate->id)
        ->assertJsonPath('lines.0.note', null)
        ->assertJsonPath('lines.1.catalog_id', $service->id)
        ->assertJsonPath('questions.0', 'What finish do they want?')
        ->assertJsonPath('catalog_truncated', false);

    // The unsure, unstated-quantity line is flagged for a second look.
    expect($response->json('lines.1.note'))->toContain('Not sure')->toContain('quantity is a guess');
});

test('ids the model invents or takes from another shop are dropped to no match', function () {
    [$shop, $owner] = aiShop();
    $otherShop = Shop::factory()->create();
    $foreign = Material::factory()->create(['shop_id' => $otherShop->id, 'name' => 'Secret Titanium']);
    $mine = Material::factory()->create(['shop_id' => $shop->id]);
    $tool = Tool::factory()->create(['shop_id' => $shop->id]);

    Http::fake(['api.anthropic.com/*' => Http::response(aiAnswer([
        aiLine(['catalog_id' => $foreign->id]),          // another shop's id
        aiLine(['catalog_id' => 999999]),                 // hallucinated id
        aiLine(['type' => 'tooling', 'catalog_id' => $mine->id]), // a material id used in the tooling list
        aiLine(['type' => 'tooling', 'catalog_id' => $tool->id]), // legitimate
    ]))]);

    $lines = $this->actingAs($owner)->postJson(route('quotes.ai-draft'), ['message' => REQUEST_TEXT])
        ->assertOk()->json('lines');

    expect($lines[0]['catalog_id'])->toBeNull()
        ->and($lines[0]['note'])->toContain('No catalog match')
        ->and($lines[1]['catalog_id'])->toBeNull()
        ->and($lines[2]['catalog_id'])->toBeNull()
        ->and($lines[3]['catalog_id'])->toBe($tool->id);
});

test('malformed lines are skipped and quantities are made sane', function () {
    [, $owner] = aiShop();
    Http::fake(['api.anthropic.com/*' => Http::response(aiAnswer([
        aiLine(['type' => 'banana']),
        aiLine(['description' => '   ']),
        aiLine(['quantity' => -4, 'description' => 'Bad qty']),
        aiLine(['confidence' => 7, 'description' => 'Over-confident']),
    ]))]);

    $lines = $this->actingAs($owner)->postJson(route('quotes.ai-draft'), ['message' => REQUEST_TEXT])
        ->assertOk()->json('lines');

    expect($lines)->toHaveCount(2)
        ->and($lines[0]['quantity'])->toEqual(1)
        ->and($lines[1]['confidence'])->toEqual(1);
});

test('only names and ids go to the provider - no costs, no other shop\'s catalog', function () {
    [$shop, $owner] = aiShop();
    Material::factory()->create(['shop_id' => $shop->id, 'name' => 'My Own Plate']);
    Material::factory()->create(['shop_id' => Shop::factory()->create()->id, 'name' => 'Rival Shop Plate']);
    Equipment::factory()->create(['shop_id' => $shop->id, 'name' => 'Plasma Table', 'rate' => 987.65]);
    Http::fake(['api.anthropic.com/*' => Http::response(aiAnswer([]))]);

    $this->actingAs($owner)->postJson(route('quotes.ai-draft'), ['message' => REQUEST_TEXT])->assertOk();

    Http::assertSent(function (Request $request) {
        $body = json_encode($request->data());

        return str_contains($body, 'My Own Plate')
            && str_contains($body, 'Plasma Table')
            && ! str_contains($body, 'Rival Shop Plate')
            && ! str_contains($body, '987.65')
            // The customer's text is fenced off as data, not instructions.
            && preg_match('/<customer_message_[a-z0-9]{12}>.*<\/customer_message_[a-z0-9]{12}>/s', $request['messages'][0]['content']) === 1
            && str_contains($request['system'], 'untrusted data');
    });
});

test('a draft is logged for usage, without keeping the customer\'s text', function () {
    [$shop, $owner] = aiShop();
    Http::fake(['api.anthropic.com/*' => Http::response(aiAnswer([aiLine()]))]);

    $this->actingAs($owner)->postJson(route('quotes.ai-draft'), ['message' => REQUEST_TEXT])->assertOk();

    $row = AiRequest::withoutGlobalScopes()->sole();
    expect($row->shop_id)->toBe($shop->id)
        ->and($row->user_id)->toBe($owner->id)
        ->and($row->feature)->toBe('quote-intake')
        ->and($row->provider)->toBe('anthropic')
        ->and($row->input_tokens)->toBe(400)
        ->and($row->output_tokens)->toBe(90)
        ->and($row->status)->toBe('ok');

    expect(json_encode(DB::table('ai_requests')->get()))->not->toContain('A36');
});

test('a provider failure is a clear message and is logged as an error', function () {
    [, $owner] = aiShop();
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'nope'], 429)]);

    $this->actingAs($owner)->postJson(route('quotes.ai-draft'), ['message' => REQUEST_TEXT])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'rate limiting'));

    expect(AiRequest::withoutGlobalScopes()->sole()->status)->toBe('error');
});

test('the message is required and has a length limit instead of being cut short', function () {
    [, $owner] = aiShop();
    Http::fake();

    $this->actingAs($owner)->postJson(route('quotes.ai-draft'), ['message' => 'hi'])->assertJsonValidationErrors('message');
    $this->actingAs($owner)->postJson(route('quotes.ai-draft'), ['message' => str_repeat('a', config('ai.max_message_length') + 1)])
        ->assertJsonValidationErrors('message');

    Http::assertNothingSent();
});

// --- access ------------------------------------------------------------------

test('after the trial on free, drafting is refused and nothing is sent', function () {
    $this->seed(PlanSeeder::class);
    $shop = Shop::factory()->create(['plan_id' => Plan::where('key', 'free')->value('id'), 'trial_ends_at' => now()->subDay()]);
    ShopAiSetting::factory()->create(['shop_id' => $shop->id]);
    Http::fake();

    $this->actingAs(actingAsShopMember($shop))->postJson(route('quotes.ai-draft'), ['message' => REQUEST_TEXT])->assertForbidden();

    Http::assertNothingSent();
});

test('a shop with no key gets a pointer to settings, not a crash', function () {
    $this->seed(PlanSeeder::class);
    $shop = Shop::factory()->create(['plan_id' => Plan::where('key', 'start-up')->value('id'), 'trial_ends_at' => now()->subDay()]);
    Http::fake();

    $this->actingAs(actingAsShopMember($shop))->postJson(route('quotes.ai-draft'), ['message' => REQUEST_TEXT])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Settings > AI'));
});

test('a member denied quotes.create cannot draft', function () {
    [$shop] = aiShop();
    $staff = actingAsShopRole($shop, 'Staff');
    DB::table('shop_user')->where(['shop_id' => $shop->id, 'user_id' => $staff->id])
        ->update(['permission_overrides' => json_encode(['quotes.create' => false])]);
    Http::fake();

    $this->actingAs($staff->fresh())->postJson(route('quotes.ai-draft'), ['message' => REQUEST_TEXT])->assertForbidden();

    Http::assertNothingSent();
});

test('the quote form is told whether AI is available and configured', function () {
    [$shop, $owner] = aiShop();

    $this->actingAs($owner)->get(route('quotes.create'))
        ->assertInertia(fn ($page) => $page->where('ai.available', true)->where('ai.configured', true));

    ShopAiSetting::withoutGlobalScopes()->delete();

    $this->actingAs($owner->fresh())->get(route('quotes.create'))
        ->assertInertia(fn ($page) => $page->where('ai.available', true)->where('ai.configured', false));
});

test('one shop\'s usage log is invisible to another', function () {
    [$shopA, $ownerA] = aiShop();
    $shopB = Shop::factory()->create();
    AiRequest::factory()->create(['shop_id' => $shopB->id]);
    AiRequest::factory()->create(['shop_id' => $shopA->id]);

    $this->actingAs($ownerA);

    expect(AiRequest::count())->toBe(1)
        ->and(AiRequest::sole()->shop_id)->toBe($shopA->id);
});
