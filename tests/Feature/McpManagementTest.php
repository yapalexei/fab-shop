<?php

use App\Actions\CreateShopAgent;
use App\Models\Shop;
use App\Models\User;
use App\Support\AgentAccess;

test('the owner turns the connection on and gets a read-only agent', function () {
    [$shop, $owner] = mcpOwnerShop();

    $this->actingAs($owner)->post(route('shop.ai.agent.store'))->assertRedirect();

    $agent = $shop->agents()->sole();
    expect($agent->is_agent)->toBeTrue()
        ->and($agent->hasShopPermission('quotes.view'))->toBeTrue()
        ->and($agent->hasShopPermission('quotes.update'))->toBeFalse();

    // Pressing it again doesn't create a second one.
    $this->actingAs($owner)->post(route('shop.ai.agent.store'));
    expect($shop->agents()->count())->toBe(1);
});

test('only the owner can manage the connection', function () {
    [$shop] = mcpOwnerShop();
    (new CreateShopAgent)($shop);

    foreach (['Admin', 'Staff'] as $role) {
        $member = actingAsShopRole($shop, $role);

        $this->actingAs($member)->post(route('shop.ai.agent.store'))->assertForbidden();
        $this->actingAs($member)->put(route('shop.ai.agent.update'), ['permissions' => ['customers.view']])->assertForbidden();
        $this->actingAs($member)->post(route('shop.ai.agent.tokens.store'), ['name' => 'x'])->assertForbidden();
        $this->actingAs($member)->delete(route('shop.ai.agent.destroy'))->assertForbidden();
    }

    expect($shop->agents()->first()->tokens()->count())->toBe(0);
});

test('the owner chooses what the agent can read, and only reading can be granted', function () {
    [$shop, $owner] = mcpOwnerShop();
    $agent = (new CreateShopAgent)($shop);

    $this->actingAs($owner)->put(route('shop.ai.agent.update'), ['permissions' => ['customers.view', 'financials.view']])
        ->assertSessionHasNoErrors();

    $agent = $agent->fresh();
    expect($agent->hasShopPermission('customers.view'))->toBeTrue()
        ->and($agent->hasShopPermission('financials.view'))->toBeTrue()
        ->and($agent->hasShopPermission('quotes.view'))->toBeFalse()
        ->and($agent->hasShopPermission('customers.update'))->toBeFalse()
        ->and($agent->hasShopPermission('customers.delete'))->toBeFalse();

    // Things that aren't readable resources (team, shop settings) or aren't real are refused.
    foreach (['team', 'shop-settings', 'quickbooks', 'customers.delete', 'nonsense'] as $bad) {
        $this->actingAs($owner)->put(route('shop.ai.agent.update'), ['permissions' => [$bad]])->assertSessionHasErrors('permissions.0');
    }

    // And an empty selection is a valid "see nothing".
    $this->actingAs($owner)->put(route('shop.ai.agent.update'), ['permissions' => []])->assertSessionHasNoErrors();
    expect($agent->fresh()->hasShopPermission('customers.view'))->toBeFalse();
});

test('a token made in settings is shown once, never listed, and really connects', function () {
    [$shop, $owner] = mcpOwnerShop();
    (new CreateShopAgent)($shop);

    $this->actingAs($owner)->post(route('shop.ai.agent.tokens.store'), ['name' => 'Claude Desktop'])
        ->assertRedirect()->assertSessionHas('new_token');

    $secret = session('new_token');
    expect($secret)->toBeString()->toContain('|');

    // The very next page load carries it...
    $first = $this->actingAs($owner)->get(route('shop.ai.edit'))
        ->assertInertia(fn ($page) => $page->where('mcp.new_token', $secret)->where('mcp.tokens.0.name', 'Claude Desktop'));
    expect(json_encode($first->viewData('page')['props']['mcp']['tokens']))->not->toContain($secret);

    // ...and the one after doesn't.
    $this->actingAs($owner)->get(route('shop.ai.edit'))
        ->assertInertia(fn ($page) => $page->where('mcp.new_token', null));

    // It works against the real endpoint.
    app('auth')->forgetGuards();
    $this->withToken($secret)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk();
});

test('a token expires after the chosen number of days, defaulting to the configured lifetime', function () {
    [$shop, $owner] = mcpOwnerShop();
    $agent = (new CreateShopAgent)($shop);
    config(['ai.mcp_token_days' => 90]);

    $this->actingAs($owner)->post(route('shop.ai.agent.tokens.store'), ['name' => 'default']);
    $this->actingAs($owner)->post(route('shop.ai.agent.tokens.store'), ['name' => 'short', 'expires_in_days' => 7]);

    $tokens = $agent->tokens()->get()->keyBy('name');
    expect((int) round(now()->diffInDays($tokens['default']->expires_at)))->toBe(90)
        ->and((int) round(now()->diffInDays($tokens['short']->expires_at)))->toBe(7);

    $this->actingAs($owner)->post(route('shop.ai.agent.tokens.store'), ['name' => '', 'expires_in_days' => 9999])
        ->assertSessionHasErrors(['name', 'expires_in_days']);
});

test('revoking a token disconnects that client immediately', function () {
    [$shop, $owner] = mcpOwnerShop();
    $agent = (new CreateShopAgent)($shop);
    $secret = $agent->createToken('laptop', ['mcp'])->plainTextToken;

    $this->withToken($secret)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk();

    // The /mcp call above leaves sanctum as the default guard for the rest of
    // this process; the owner's browser request below is a `web` one.
    app('auth')->shouldUse('web');

    $this->actingAs($owner)->delete(route('shop.ai.agent.tokens.destroy', $agent->tokens()->first()->id))->assertRedirect();

    app('auth')->forgetGuards();
    $this->withToken($secret)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
});

test('one shop cannot revoke another shop\'s token', function () {
    [$shopA, $ownerA] = mcpOwnerShop();
    [$shopB] = mcpOwnerShop();
    (new CreateShopAgent)($shopA);
    $theirToken = (new CreateShopAgent)($shopB)->createToken('theirs', ['mcp'])->accessToken;

    $this->actingAs($ownerA)->delete(route('shop.ai.agent.tokens.destroy', $theirToken->id))->assertNotFound();

    expect($theirToken->fresh())->not->toBeNull();
});

test('turning it off deletes the agent and every token', function () {
    [$shop, $owner] = mcpOwnerShop();
    $agent = (new CreateShopAgent)($shop);
    $secret = $agent->createToken('a', ['mcp'])->plainTextToken;
    $agent->createToken('b', ['mcp']);

    $this->actingAs($owner)->delete(route('shop.ai.agent.destroy'))->assertRedirect();

    expect($shop->agents()->count())->toBe(0)
        ->and(User::find($agent->id))->toBeNull()
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);

    app('auth')->forgetGuards();
    $this->withToken($secret)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
});

test('without ai access the connection cannot be changed, but the page explains', function () {
    [$shop, $owner] = mcpOwnerShop('free');

    $this->actingAs($owner)->post(route('shop.ai.agent.store'))->assertForbidden();
    $this->actingAs($owner)->get(route('shop.ai.edit'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('available', false)->where('mcp.enabled', false));

    expect($shop->agents()->count())->toBe(0);
});

test('default access never includes money or suppliers', function () {
    expect(AgentAccess::defaults())->not->toContain('financials.view')->not->toContain('business-partners.view')
        ->and(array_keys(AgentAccess::grantable()))->not->toContain('team')->not->toContain('shop-settings');
});

test('the owner can allow changes, and a change brings its view along', function () {
    [$shop, $owner] = mcpOwnerShop();
    $agent = (new CreateShopAgent)($shop);

    $this->actingAs($owner)->put(route('shop.ai.agent.update'), ['permissions' => ['quotes.create', 'customers.update', 'jobs.update']])
        ->assertSessionHasNoErrors();

    $agent = $agent->fresh();
    expect($agent->hasShopPermission('quotes.create'))->toBeTrue()
        ->and($agent->hasShopPermission('quotes.view'))->toBeTrue()          // implied
        ->and($agent->hasShopPermission('customers.view'))->toBeTrue()       // implied
        ->and($agent->hasShopPermission('jobs.update'))->toBeTrue()
        ->and($agent->hasShopPermission('quotes.update'))->toBeFalse()        // not granted
        ->and($agent->hasShopPermission('customers.create'))->toBeFalse();
});

test('write permissions can never include deletes, sending, cancelling or anything outside the AI\'s resources', function () {
    [$shop, $owner] = mcpOwnerShop();
    (new CreateShopAgent)($shop);

    foreach (['quotes.delete', 'quotes.send', 'jobs.cancel', 'jobs.create', 'team.update', 'markup.update', 'shop-settings.update', 'quickbooks.update'] as $bad) {
        $this->actingAs($owner)->put(route('shop.ai.agent.update'), ['permissions' => [$bad]])->assertSessionHasErrors('permissions.0');
    }

    expect($shop->agents()->first()->hasShopPermission('quotes.delete'))->toBeFalse();
});

test('the settings page describes every grantable resource and what the agent currently holds', function () {
    [$shop, $owner] = mcpOwnerShop();
    $agent = (new CreateShopAgent)($shop);
    $shop->agents()->updateExistingPivot($agent->id, ['permission_overrides' => AgentAccess::overridesFor(['quotes.create'])]);

    $this->actingAs($owner)->get(route('shop.ai.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('mcp.permissions', ['quotes.view', 'quotes.create'])
            ->where('mcp.grantable.quotes.actions', ['view', 'create', 'update'])
            ->where('mcp.grantable.jobs.actions', ['view', 'update'])
            ->where('mcp.grantable.financials.actions', ['view'])
            ->missing('mcp.grantable.team')
            ->missing('mcp.grantable.shop-settings'));
});
