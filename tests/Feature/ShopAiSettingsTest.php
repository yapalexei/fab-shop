<?php

use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopAiSetting;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Ai\AnthropicTextModel;
use App\Services\Ai\OpenAiTextModel;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const ANTHROPIC_OK = [
    'model' => 'claude-sonnet-5-5',
    'stop_reason' => 'end_turn',
    'content' => [['type' => 'text', 'text' => 'ok']],
    'usage' => ['input_tokens' => 12, 'output_tokens' => 2],
];

function shopOnPlan(?string $planKey, bool $trialEnded = true): Shop
{
    test()->seed(PlanSeeder::class);

    return Shop::factory()->create([
        'plan_id' => $planKey ? Plan::where('key', $planKey)->value('id') : null,
        'trial_ends_at' => $trialEnded ? now()->subDay() : now()->addDays(14),
    ]);
}

function validAiPayload(array $overrides = []): array
{
    return array_merge([
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-5-5',
        'api_key' => 'sk-ant-secret-key-1234',
    ], $overrides);
}

// --- entitlement: trial, Start-up, and the lapse in between -----------------

test('ai assist is included in shop (start-up) and pro but not solo or free', function () {
    $this->seed(PlanSeeder::class);

    foreach (['start-up', 'pro'] as $key) {
        expect(Plan::where('key', $key)->first()->hasFeature(Feature::AiAssist))->toBeTrue();
    }
    foreach (['free', 'solo'] as $key) {
        expect(Plan::where('key', $key)->first()->hasFeature(Feature::AiAssist))->toBeFalse();
    }
});

test('ai agent (MCP) access is Pro-only', function () {
    $this->seed(PlanSeeder::class);

    expect(Plan::where('key', 'pro')->first()->hasFeature(Feature::AiAgent))->toBeTrue();
    foreach (['free', 'solo', 'start-up'] as $key) {
        expect(Plan::where('key', $key)->first()->hasFeature(Feature::AiAgent))->toBeFalse();
    }
});

test('ai works during the trial, stops when it ends on free, and resumes on start-up with the saved key', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(ANTHROPIC_OK)]);
    $shop = shopOnPlan(null, trialEnded: false);
    ShopAiSetting::factory()->create(['shop_id' => $shop->id]);

    expect(fn () => app(AiManager::class)->textModelFor($shop->fresh()))->not->toThrow(AiException::class);

    // Trial runs out, shop drops to Free.
    $shop->update(['trial_ends_at' => now()->subDay(), 'plan_id' => Plan::where('key', 'free')->value('id')]);
    expect(fn () => app(AiManager::class)->textModelFor($shop->fresh()))->toThrow(AiException::class);

    // The key survived the lapse...
    expect($shop->fresh()->aiSetting->hasKey())->toBeTrue();

    // ...and works again the moment they subscribe to Start-up.
    $shop->update(['plan_id' => Plan::where('key', 'start-up')->value('id')]);
    expect(fn () => app(AiManager::class)->textModelFor($shop->fresh()))->not->toThrow(AiException::class);
});

test('a shop with no key configured gets a clear not-configured error', function () {
    $shop = shopOnPlan('start-up');

    expect(fn () => app(AiManager::class)->textModelFor($shop))
        ->toThrow(AiException::class, 'not set up');
});

test('after the trial the settings page explains that ai is paused and saving is refused', function () {
    Http::fake();
    $shop = shopOnPlan('free');
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->get(route('shop.ai.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ShopSettings/Ai')->where('available', false));

    $this->actingAs($owner)->put(route('shop.ai.update'), validAiPayload())->assertForbidden();
    expect(ShopAiSetting::withoutGlobalScopes()->count())->toBe(0);
    Http::assertNothingSent();
});

// --- saving, testing and protecting the key ---------------------------------

test('the owner saves a key, it is tested first, stored encrypted and never sent back', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(ANTHROPIC_OK)]);
    $shop = shopOnPlan('start-up');
    $owner = actingAsShopMember($shop);

    $this->actingAs($owner)->put(route('shop.ai.update'), validAiPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.anthropic.com/v1/messages'
        && $request->hasHeader('x-api-key', 'sk-ant-secret-key-1234')
        && $request->hasHeader('anthropic-version', '2023-06-01')
        && $request['model'] === 'claude-sonnet-5-5');

    $setting = $shop->fresh()->aiSetting;
    expect($setting->api_key)->toBe('sk-ant-secret-key-1234')
        ->and($setting->last_tested_at)->not->toBeNull();

    // Ciphertext on disk, not the key.
    $raw = DB::table('shop_ai_settings')->where('shop_id', $shop->id)->value('api_key');
    expect($raw)->not->toContain('sk-ant-secret-key-1234');

    // The page gets a hint only. (Reload the user: in a test the same
    // object lives across requests, so its cached shop->aiSetting is stale.)
    $response = $this->actingAs($owner->fresh())->get(route('shop.ai.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('setting.has_key', true)
            ->where('setting.key_hint', '…1234')
            ->missing('setting.api_key'));

    expect(json_encode($response->viewData('page')))->not->toContain('sk-ant-secret-key-1234');
});

test('a key the provider rejects is not stored and the error does not leak the key', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'invalid x-api-key'], 401)]);
    $shop = shopOnPlan('start-up');
    $owner = actingAsShopMember($shop);

    $response = $this->actingAs($owner)->put(route('shop.ai.update'), validAiPayload());

    $response->assertSessionHasErrors('api_key');
    expect(session('errors')->first('api_key'))->toContain('rejected the API key')
        ->not->toContain('sk-ant-secret-key-1234')
        ->and(ShopAiSetting::withoutGlobalScopes()->count())->toBe(0);
    // And it is not flashed back as old input.
    expect(session()->getOldInput())->not->toHaveKey('api_key');
});

test('changing only the model keeps the stored key without re-entering it', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(ANTHROPIC_OK)]);
    $shop = shopOnPlan('start-up');
    $owner = actingAsShopMember($shop);
    ShopAiSetting::factory()->create(['shop_id' => $shop->id, 'api_key' => 'sk-ant-original-9999']);

    $this->actingAs($owner)->put(route('shop.ai.update'), validAiPayload(['model' => 'claude-haiku-4-5', 'api_key' => '']))
        ->assertSessionHasNoErrors();

    $setting = $shop->fresh()->aiSetting;
    expect($setting->model)->toBe('claude-haiku-4-5')
        ->and($setting->api_key)->toBe('sk-ant-original-9999');
});

test('switching provider requires a new key', function () {
    Http::fake();
    $shop = shopOnPlan('start-up');
    $owner = actingAsShopMember($shop);
    ShopAiSetting::factory()->create(['shop_id' => $shop->id]);

    $this->actingAs($owner)->put(route('shop.ai.update'), validAiPayload(['provider' => 'openai', 'model' => 'gpt-4o', 'api_key' => '']))
        ->assertSessionHasErrors('api_key');

    expect($shop->fresh()->aiSetting->provider)->toBe('anthropic');
    Http::assertNothingSent();
});

test('provider and model must come from the curated list', function () {
    Http::fake();
    $owner = actingAsShopMember(shopOnPlan('start-up'));

    $this->actingAs($owner)->put(route('shop.ai.update'), validAiPayload(['provider' => 'evil-corp']))->assertSessionHasErrors('provider');
    $this->actingAs($owner)->put(route('shop.ai.update'), validAiPayload(['model' => 'gpt-4o']))->assertSessionHasErrors('model');
    Http::assertNothingSent();
});

test('only the owner can see or change ai settings', function () {
    Http::fake();
    $shop = shopOnPlan('start-up');

    foreach (['Admin', 'Staff'] as $role) {
        $member = actingAsShopRole($shop, $role);
        $this->actingAs($member)->get(route('shop.ai.edit'))->assertForbidden();
        $this->actingAs($member)->put(route('shop.ai.update'), validAiPayload())->assertForbidden();
        $this->actingAs($member)->delete(route('shop.ai.destroy'))->assertForbidden();
    }
    Http::assertNothingSent();
});

test('the owner can remove the key', function () {
    $shop = shopOnPlan('start-up');
    $owner = actingAsShopMember($shop);
    ShopAiSetting::factory()->create(['shop_id' => $shop->id]);

    $this->actingAs($owner)->delete(route('shop.ai.destroy'))->assertRedirect();

    expect(ShopAiSetting::withoutGlobalScopes()->count())->toBe(0);
});

// --- tenant isolation -------------------------------------------------------

test('a shop cannot see or use another shop\'s ai settings', function () {
    Http::fake();
    $shopA = shopOnPlan('start-up');
    $shopB = Shop::factory()->create(['plan_id' => $shopA->plan_id, 'trial_ends_at' => now()->subDay()]);
    ShopAiSetting::factory()->create(['shop_id' => $shopB->id, 'api_key' => 'sk-shop-b-secret-0000']);

    $ownerA = actingAsShopMember($shopA);

    $this->actingAs($ownerA)->get(route('shop.ai.edit'))
        ->assertInertia(fn ($page) => $page->where('setting', null));

    expect(ShopAiSetting::count())->toBe(0)
        ->and(fn () => app(AiManager::class)->textModelFor($shopA->fresh()))->toThrow(AiException::class, 'not set up');

    // Deleting from A must not touch B's row.
    $this->actingAs($ownerA)->delete(route('shop.ai.destroy'))->assertRedirect();
    expect(ShopAiSetting::withoutGlobalScopes()->where('shop_id', $shopB->id)->exists())->toBeTrue();
});

// --- provider drivers -------------------------------------------------------

test('the anthropic driver sends a structured-output request and parses the answer', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'model' => 'claude-sonnet-5-5',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => '{"lines":[{"description":"1/4in plate"}]}']],
        'usage' => ['input_tokens' => 50, 'output_tokens' => 20],
    ])]);

    $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['lines'], 'properties' => ['lines' => ['type' => 'array']]];
    $response = (new AnthropicTextModel('sk-x', 'claude-sonnet-5-5'))->complete('system', 'prompt', $schema);

    expect($response->data)->toBe(['lines' => [['description' => '1/4in plate']]])
        ->and($response->inputTokens)->toBe(50)
        ->and($response->outputTokens)->toBe(20);

    Http::assertSent(fn (Request $request) => $request['output_config']['format']['type'] === 'json_schema'
        && $request['system'] === 'system'
        && ! isset($request['temperature'])
        && ! isset($request['thinking']));
});

test('the openai driver sends a bearer token and a json schema response format', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'model' => 'gpt-4o',
        'choices' => [['message' => ['content' => '{"ok":true}']]],
        'usage' => ['prompt_tokens' => 9, 'completion_tokens' => 3],
    ])]);

    $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['ok'], 'properties' => ['ok' => ['type' => 'boolean']]];
    $response = (new OpenAiTextModel('sk-openai', 'gpt-4o'))->complete('sys', 'hi', $schema);

    expect($response->data)->toBe(['ok' => true])->and($response->inputTokens)->toBe(9);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer sk-openai')
        && $request['response_format']['type'] === 'json_schema'
        && isset($request['max_completion_tokens']));
});

test('provider failures become safe, specific errors', function (int $status, string $expected) {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'raw provider text sk-leak']], $status)]);

    try {
        (new AnthropicTextModel('sk-x', 'claude-sonnet-5-5'))->complete('s', 'p');
        $this->fail('expected AiException');
    } catch (AiException $e) {
        expect($e->getMessage())->toContain($expected)->not->toContain('sk-leak');
    }
})->with([
    'bad key' => [401, 'rejected the API key'],
    'unknown model' => [404, 'does not recognise this model'],
    'rate limited' => [429, 'rate limiting'],
    'provider down' => [503, 'having trouble'],
]);

test('a refusal or unreadable structured answer is an error, not bad data', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['stop_reason' => 'refusal', 'content' => []])
        ->push(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'not json']], 'usage' => []]),
    ]);

    $model = new AnthropicTextModel('sk-x', 'claude-sonnet-5-5');

    expect(fn () => $model->complete('s', 'p'))->toThrow(AiException::class, 'declined');
    expect(fn () => $model->complete('s', 'p', ['type' => 'object']))->toThrow(AiException::class, 'could not be read');
});
