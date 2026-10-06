<?php

use App\Actions\CreateShopAgent;
use App\Enums\QuoteStatus;
use App\Mcp\Servers\ShopServer;
use App\Mcp\Tools\GetJobTool;
use App\Mcp\Tools\GetQuoteTool;
use App\Mcp\Tools\ListJobsTool;
use App\Mcp\Tools\ListQuotesTool;
use App\Mcp\Tools\SearchCustomersTool;
use App\Models\Customer;
use App\Models\McpRequest;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Shop;
use App\Models\User;
use App\Support\AgentAccess;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;

/** A Pro shop (AI agent access included), its owner, and its agent. */
function mcpShop(array $shopOverrides = []): array
{
    test()->seed(PlanSeeder::class);
    $shop = Shop::factory()->create([
        'plan_id' => Plan::where('key', 'pro')->value('id'),
        'trial_ends_at' => now()->subDay(),
        ...$shopOverrides,
    ]);

    return [$shop, actingAsShopMember($shop), (new CreateShopAgent)($shop)];
}

function mcpToken(User $agent, array $abilities = ['mcp']): string
{
    return $agent->createToken('test', $abilities)->plainTextToken;
}

/** Replace what the agent may do (permission names, e.g. ['quotes.view', 'quotes.create']). */
function mcpGrant(Shop $shop, User $agent, array $permissions): void
{
    $shop->agents()->updateExistingPivot($agent->id, ['permission_overrides' => AgentAccess::overridesFor($permissions)]);
}

function mcpCall(string $token, string $method, array $params = [])
{
    // One app instance serves every request in a test, and the token guard
    // remembers who it authenticated - a real client's requests don't share
    // that, so start each call clean.
    app('auth')->forgetGuards();

    return test()->withToken($token)->postJson('/mcp', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params,
    ]);
}

function sentQuote(Shop $shop, array $overrides = []): Quote
{
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    $quote = Quote::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'status' => QuoteStatus::Sent,
        'sent_at' => now()->subDays(10),
        ...$overrides,
    ]);
    QuoteLineItem::factory()->create(['quote_id' => $quote->id, 'quantity' => 2, 'unit_cost' => 50, 'markup_percent' => 0]);

    return $quote;
}

// --- the agent identity ------------------------------------------------------

test('an agent is one-shop, role-less and read-only by default', function () {
    [$shop, , $agent] = mcpShop();

    expect($agent->is_agent)->toBeTrue()
        ->and($agent->current_shop_id)->toBe($shop->id)
        ->and($agent->email)->toEndWith('@agents.invalid')
        ->and($agent->getRoleNames())->toBeEmpty()
        ->and($agent->hasShopPermission('quotes.view'))->toBeTrue()
        ->and($agent->hasShopPermission('quotes.update'))->toBeFalse()
        ->and($agent->hasShopPermission('financials.view'))->toBeFalse()
        ->and($agent->hasShopPermission('team.view'))->toBeFalse()
        ->and($agent->shops()->count())->toBe(1);
});

test('creating the agent twice returns the same one', function () {
    [$shop, , $agent] = mcpShop();

    expect((new CreateShopAgent)($shop)->id)->toBe($agent->id)
        ->and(User::where('is_agent', true)->count())->toBe(1);
});

test('an agent is not a team member: no seat, no team row, not an employee or assignee option', function () {
    [$shop, $owner, $agent] = mcpShop();
    $people = $shop->members()->count();

    expect($shop->members()->pluck('users.id')->all())->not->toContain($agent->id)
        ->and($shop->agents()->pluck('users.id')->all())->toBe([$agent->id]);

    $this->actingAs($owner)->get(route('team.index'))
        ->assertInertia(fn ($page) => $page->has('members', $people)
            ->where('members', fn ($members) => ! collect($members)->pluck('id')->contains($agent->id)));

    $this->actingAs($owner)->get(route('quotes.create'))
        ->assertInertia(fn ($page) => $page->has('employees', $people));
});

test('an agent can never sign in through the login form', function () {
    [, , $agent] = mcpShop();
    $agent->forceFill(['password' => 'a-known-password'])->save();

    $this->post(route('login'), ['email' => $agent->email, 'password' => 'a-known-password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

// --- who gets through the door -----------------------------------------------

test('no token, a wrong token and a browser session are all refused', function () {
    [, $owner] = mcpShop();

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    $this->withToken('not-a-real-token')->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    // A logged-in human's session cookie must not authenticate MCP.
    $this->actingAs($owner)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
});

test('a person\'s own token can never be used for MCP', function () {
    [, $owner] = mcpShop();

    mcpCall(mcpToken($owner), 'tools/list')->assertForbidden();
});

test('an agent token without the mcp ability is refused', function () {
    [, , $agent] = mcpShop();

    mcpCall(mcpToken($agent, ['something-else']), 'tools/list')->assertForbidden();
});

test('a valid agent token lists exactly the tools its access allows', function () {
    [$shop, , $agent] = mcpShop();
    $token = mcpToken($agent);

    $names = fn () => collect(mcpCall($token, 'tools/list')->assertOk()->json('result.tools'))->pluck('name')->sort()->values()->all();

    // Read-only by default: the access report plus the read tools, no writes.
    expect($names())->toBe(['get-access', 'get-job', 'get-quote', 'list-job-stages', 'list-jobs', 'list-quotes', 'search-catalog', 'search-customers']);

    mcpGrant($shop, $agent->fresh(), ['customers.view']);
    expect($names())->toBe(['get-access', 'search-customers']);

    // Even with nothing granted, the AI can still ask what it may do.
    mcpGrant($shop, $agent->fresh(), []);
    expect($names())->toBe(['get-access']);
});

test('ai access lapsing stops the endpoint, and resubscribing restores it with the same token', function () {
    [$shop, , $agent] = mcpShop();
    $token = mcpToken($agent);

    mcpCall($token, 'tools/list')->assertOk();

    $shop->update(['plan_id' => Plan::where('key', 'free')->value('id')]);
    mcpCall($token, 'tools/list')->assertForbidden();

    $shop->update(['plan_id' => Plan::where('key', 'pro')->value('id')]);
    mcpCall($token, 'tools/list')->assertOk();
});

test('a suspended shop\'s agent is refused', function () {
    [$shop, , $agent] = mcpShop();
    $shop->forceFill(['suspended_at' => now()])->save();

    mcpCall(mcpToken($agent), 'tools/list')->assertForbidden();
});

test('a revoked token stops working', function () {
    [, , $agent] = mcpShop();
    $token = $agent->createToken('x', ['mcp']);

    mcpCall($token->plainTextToken, 'tools/list')->assertOk();
    $token->accessToken->delete();
    app('auth')->forgetGuards();

    mcpCall($token->plainTextToken, 'tools/list')->assertUnauthorized();
});

test('an expired token stops working', function () {
    [, , $agent] = mcpShop();
    $token = $agent->createToken('x', ['mcp'], now()->subMinute())->plainTextToken;

    mcpCall($token, 'tools/list')->assertUnauthorized();
});

// --- tenant isolation --------------------------------------------------------

test('an agent only ever sees its own shop, however the humans switch around', function () {
    [$shopA, $ownerA, $agentA] = mcpShop();
    [$shopB] = mcpShop();
    $mine = sentQuote($shopA);
    $theirs = sentQuote($shopB);
    $theirCustomer = $theirs->customer->name; // read before the tenant scope is active

    // The same person owns/belongs to both shops and flips between them.
    $shopB->members()->attach($ownerA);
    $ownerA->current_shop_id = $shopB->id;
    $ownerA->save();

    $quotes = ShopServer::actingAs($agentA)->tool(ListQuotesTool::class, []);
    $quotes->assertOk()->assertSee('"id":'.$mine->id)->assertDontSee('"id":'.$theirs->id);

    ShopServer::actingAs($agentA)->tool(GetQuoteTool::class, ['id' => $theirs->id])
        ->assertOk()->assertSee('No quote with that id');

    ShopServer::actingAs($agentA)->tool(SearchCustomersTool::class, ['query' => $theirCustomer])
        ->assertOk()->assertDontSee($theirCustomer);
});

// --- what the tools return ---------------------------------------------------

test('list-quotes filters to stale quotes using the shop\'s own window', function () {
    [$shop, , $agent] = mcpShop();
    $shop->update(['stale_quote_days' => 7]);
    $stale = sentQuote($shop, ['sent_at' => now()->subDays(9)]);
    $fresh = sentQuote($shop, ['sent_at' => now()->subDays(2)]);
    $draft = sentQuote($shop, ['status' => QuoteStatus::Draft, 'sent_at' => null]);
    $archived = sentQuote($shop, ['sent_at' => now()->subDays(30), 'archived_at' => now()]);

    $response = ShopServer::actingAs($agent)->tool(ListQuotesTool::class, ['stale_only' => true]);

    $response->assertOk()->assertSee('"id":'.$stale->id)
        ->assertDontSee('"id":'.$fresh->id)
        ->assertDontSee('"id":'.$draft->id)
        ->assertDontSee('"id":'.$archived->id)
        ->assertSee('"stale_after_days":7');
});

test('money is withheld unless the agent may see financials', function () {
    [$shop, , $agent] = mcpShop();
    $quote = sentQuote($shop); // 2 x $50, no markup = $100

    $without = ShopServer::actingAs($agent)->tool(GetQuoteTool::class, ['id' => $quote->id]);
    $without->assertOk()->assertSee('"description"')->assertDontSee('unit_cost')->assertDontSee('"total"')->assertDontSee('margin');

    ShopServer::actingAs($agent)->tool(ListQuotesTool::class, [])->assertDontSee('"total"');

    mcpGrant($shop, $agent, [...AgentAccess::defaults(), 'financials.view']);
    $agent = $agent->fresh();

    ShopServer::actingAs($agent)->tool(GetQuoteTool::class, ['id' => $quote->id])
        ->assertOk()->assertSee('"total":100')->assertSee('unit_cost');
    ShopServer::actingAs($agent)->tool(ListQuotesTool::class, [])->assertSee('"total":100');
});

test('list-jobs and get-job return stage and due date, leave out cancelled jobs, and hide price without financials', function () {
    [$shop, , $agent] = mcpShop();
    $job = jobForShop($shop);
    $job->forceFill(['due_date' => now()->subDays(3)->toDateString()])->save();
    $cancelled = jobForShop($shop);
    $cancelled->forceFill(['cancelled_at' => now()])->save();

    ShopServer::actingAs($agent)->tool(ListJobsTool::class, [])
        ->assertOk()->assertSee($job->job_number)->assertDontSee($cancelled->job_number);
    ShopServer::actingAs($agent)->tool(ListJobsTool::class, ['include_cancelled' => true])->assertSee($cancelled->job_number);
    ShopServer::actingAs($agent)->tool(ListJobsTool::class, ['overdue_only' => true])->assertSee($job->job_number);

    ShopServer::actingAs($agent)->tool(GetJobTool::class, ['id' => $job->id])
        ->assertOk()->assertSee($job->job_number)->assertSee($job->jobStage->name)->assertDontSee('"total"');
});

// --- permissions, validation, logging ----------------------------------------

test('a tool the agent has no access to is not offered and cannot be called', function () {
    [$shop, , $agent] = mcpShop();
    mcpGrant($shop, $agent, ['customers.view']);
    $agent = $agent->fresh();

    ShopServer::actingAs($agent)->tool(ListQuotesTool::class, [])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(SearchCustomersTool::class, [])->assertOk();

    // The refused call never reached the tool, so only the allowed one is logged.
    expect(McpRequest::withoutGlobalScopes()->pluck('tool')->all())->toBe(['search-customers']);
});

test('a tool re-checks permission itself even if it was somehow reached', function () {
    [$shop, , $agent] = mcpShop();
    mcpGrant($shop, $agent, ['customers.view']);

    $this->actingAs($agent->fresh());

    $response = (new ListQuotesTool)->handle(new Request([]));

    expect($response->isError())->toBeTrue()
        ->and(McpRequest::withoutGlobalScopes()->sole()->status)->toBe('denied');
});

test('bad arguments come back as a readable error', function () {
    [, , $agent] = mcpShop();

    ShopServer::actingAs($agent)->tool(ListQuotesTool::class, ['limit' => 5000])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(ListQuotesTool::class, ['status' => 'banana'])->assertHasErrors();
});

test('calls are logged with tool, status and timing but never the arguments or results', function () {
    [$shop, , $agent] = mcpShop();
    $quote = sentQuote($shop);

    ShopServer::actingAs($agent)->tool(SearchCustomersTool::class, ['query' => $quote->customer->name])->assertOk();

    $row = McpRequest::withoutGlobalScopes()->sole();
    expect($row->shop_id)->toBe($shop->id)
        ->and($row->agent_id)->toBe($agent->id)
        ->and($row->tool)->toBe('search-customers')
        ->and($row->status)->toBe('ok');
    expect(json_encode(DB::table('mcp_requests')->get()))->not->toContain($quote->customer->name);
});

test('the usage log is invisible across shops', function () {
    [$shopA, $ownerA] = mcpShop();
    [$shopB] = mcpShop();
    McpRequest::create(['shop_id' => $shopA->id, 'tool' => 'list-jobs', 'status' => 'ok']);
    McpRequest::create(['shop_id' => $shopB->id, 'tool' => 'list-jobs', 'status' => 'ok']);

    $this->actingAs($ownerA);

    expect(McpRequest::count())->toBe(1)->and(McpRequest::sole()->shop_id)->toBe($shopA->id);
});

test('no tool can change anything - every one is read-only', function () {
    [, , $agent] = mcpShop();
    $tools = collect(mcpCall(mcpToken($agent), 'tools/list')->json('result.tools'));

    expect($tools)->not->toBeEmpty();
    $tools->each(fn ($tool) => expect($tool['annotations']['readOnlyHint'] ?? null)->toBeTrue());
});
