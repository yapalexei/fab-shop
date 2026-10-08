<?php

use App\Enums\QuoteStatus;
use App\Mcp\Servers\ShopServer;
use App\Mcp\Tools\Catalog\CreateBusinessPartnerTool;
use App\Mcp\Tools\Catalog\CreateEquipmentTool;
use App\Mcp\Tools\Catalog\CreateMaterialTool;
use App\Mcp\Tools\Catalog\CreateServiceTool;
use App\Mcp\Tools\Catalog\CreateToolTool;
use App\Mcp\Tools\Catalog\UpdateBusinessPartnerTool;
use App\Mcp\Tools\Catalog\UpdateEquipmentTool;
use App\Mcp\Tools\Catalog\UpdateMaterialTool;
use App\Mcp\Tools\Catalog\UpdateServiceTool;
use App\Mcp\Tools\Catalog\UpdateToolTool;
use App\Mcp\Tools\CreateCustomerTool;
use App\Mcp\Tools\CreateQuoteTool;
use App\Mcp\Tools\GetAccessTool;
use App\Mcp\Tools\MoveJobTool;
use App\Mcp\Tools\SearchCatalogTool;
use App\Mcp\Tools\UpdateCustomerTool;
use App\Mcp\Tools\UpdateJobTool;
use App\Mcp\Tools\UpdateQuoteTool;
use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\McpRequest;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Tool;
use App\Models\User;
use App\Support\AgentAccess;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Sanctum\TransientToken;
use Spatie\Activitylog\Models\Activity;

/** A shop whose agent has been granted exactly these permissions (view is implied by any write). */
function writeShop(array $permissions): array
{
    [$shop, $owner, $agent] = mcpShop();
    mcpGrant($shop, $agent, $permissions);

    // Called directly (not over HTTP) the agent has no token, so give it the
    // kind the owner makes by ticking "allow changes" - read-only tokens are
    // covered by McpSecurityTest.
    return [$shop, $owner, $agent->fresh()->withAccessToken(new TransientToken)];
}

/**
 * A tool call leaves the test authenticated as the agent, and the tenant scope
 * then follows it - so a factory that builds its own shop (like the job
 * factory) can no longer see that shop's rows. Drop the login before making
 * more fixtures, as a fresh request would.
 */
function loggedOut(): void
{
    app('auth')->forgetGuards();
}

const ALL_WRITES = [
    'customers.create', 'customers.update', 'quotes.create', 'quotes.update', 'jobs.update', 'job-stages.view',
    'materials.create', 'materials.update', 'services.create', 'services.update', 'equipment.create', 'equipment.update',
    'tooling.create', 'tooling.update', 'business-partners.create', 'business-partners.update', 'financials.view',
];

// --- what can be granted, and what can't ------------------------------------

test('nothing in the grantable set can delete, send, cancel or reach team, settings or billing', function () {
    $all = AgentAccess::allPermissions();

    expect($all)->not->toContain('customers.delete')->not->toContain('quotes.delete')->not->toContain('jobs.cancel')
        ->not->toContain('jobs.create')->not->toContain('team.update')->not->toContain('team.view')
        ->not->toContain('shop-settings.update')->not->toContain('markup.update')->not->toContain('quickbooks.update')
        ->and(collect($all)->filter(fn ($permission) => str_ends_with($permission, '.delete')))->toBeEmpty();

    // And submitting them anyway grants nothing.
    $overrides = AgentAccess::overridesFor(['customers.delete', 'team.update', 'shop-settings.update', 'jobs.cancel', 'customers.update']);
    expect(array_keys($overrides))->toBe(['customers.update', 'customers.view']);
});

test('a write permission brings its view along', function () {
    expect(AgentAccess::overridesFor(['quotes.create']))->toBe(['quotes.create' => true, 'quotes.view' => true]);
});

test('the server offers no tool that deletes, sends, archives or cancels', function () {
    [, , $agent] = writeShop(ALL_WRITES);
    $names = collect(mcpCall(mcpWriteToken($agent), 'tools/list')->assertOk()->json('result.tools'))->pluck('name');

    expect($names)->toContain('create-quote', 'update-quote', 'move-job', 'create-business-partner')
        ->and($names->filter(fn ($name) => preg_match('/delete|remove|send|email|archive|cancel|won|lost|convert|invoice/', $name)))->toBeEmpty();
});

test('write tools are offered only when their permission is granted', function () {
    [$shop, , $agent] = writeShop(['customers.create']);
    $names = fn () => collect(mcpCall(mcpWriteToken($agent), 'tools/list')->json('result.tools'))->pluck('name')->all();

    expect($names())->toContain('create-customer')->not->toContain('update-customer')->not->toContain('create-quote');

    mcpGrant($shop, $agent, ['customers.update']);
    expect($names())->toContain('update-customer')->not->toContain('create-customer');
});

test('write tools carry honest annotations: not read-only, never destructive', function () {
    [, , $agent] = writeShop(ALL_WRITES);
    $tools = collect(mcpCall(mcpWriteToken($agent), 'tools/list')->json('result.tools'))->keyBy('name');

    foreach (['create-customer', 'update-quote', 'move-job', 'update-tool'] as $name) {
        expect($tools[$name]['annotations']['readOnlyHint'] ?? null)->toBeFalse()
            ->and($tools[$name]['annotations']['destructiveHint'] ?? null)->toBeFalse();
    }
    expect($tools['get-access']['annotations']['readOnlyHint'])->toBeTrue();
});

// --- get-access: the AI is told what it can do --------------------------------

test('get-access tells a read-only connection it cannot change anything', function () {
    [, , $agent] = mcpShop();

    $response = ShopServer::actingAs($agent)->tool(GetAccessTool::class, [])->assertOk();

    $response->assertSee('read-only')->assertSee('"sees_money":false')->assertSee('Delete anything.');
    $writes = collect(mcpCall(mcpWriteToken($agent), 'tools/list')->json('result.tools'))->pluck('name')->filter(fn ($n) => str_starts_with($n, 'create-') || str_starts_with($n, 'update-'));
    expect($writes)->toBeEmpty();
});

test('get-access lists exactly what was granted, tool by tool', function () {
    [, , $agent] = writeShop(['customers.create', 'quotes.update', 'financials.view']);

    ShopServer::actingAs($agent)->tool(GetAccessTool::class, [])->assertOk()
        ->assertSee('"sees_money":true')
        ->assertSee('You can create or change: Customers, Quotes.')
        ->assertSee('"create-customer"')->assertDontSee('"update-customer"')
        ->assertSee('"update-quote"')->assertDontSee('"create-quote"');
});

test('get-access reports per resource what is granted and what the owner could still grant', function () {
    [, , $agent] = writeShop(['customers.create']);

    $response = mcpCall(mcpWriteToken($agent), 'tools/call', ['name' => 'get-access', 'arguments' => (object) []])->assertOk();
    $report = $response->json('result.structuredContent');

    $customers = collect($report['resources'])->firstWhere('resource', 'customers');
    expect($customers['can'])->toBe(['view', 'create'])
        ->and($customers['could_be_granted_by_owner'])->toBe(['update'])
        ->and(collect($report['resources'])->firstWhere('resource', 'quotes')['can'])->toBe([])
        ->and($report['shop'])->not->toBeEmpty()
        ->and(collect($report['tools_you_have'])->firstWhere('name', 'create-customer')['writes'])->toBeTrue()
        ->and(collect($report['tools_you_have'])->firstWhere('name', 'get-access')['writes'])->toBeFalse();
});

test('the server instructions point the AI at get-access first', function () {
    [, , $agent] = mcpShop();

    $init = mcpCall(mcpWriteToken($agent), 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']])->assertOk();

    expect($init->json('result.instructions'))->toContain('get-access');
});

// --- customers ---------------------------------------------------------------

test('create-customer creates one, refuses a same-named duplicate, and records who did it', function () {
    [$shop, , $agent] = writeShop(['customers.create']);

    ShopServer::actingAs($agent)->tool(CreateCustomerTool::class, ['name' => 'Dana Whitfield', 'email' => 'dana@example.test', 'phone' => '555-0100'])
        ->assertOk()->assertSee('"created":true')->assertSee('Dana Whitfield');

    $customer = Customer::withoutGlobalScopes()->where('shop_id', $shop->id)->sole();
    expect($customer->email)->toBe('dana@example.test');

    ShopServer::actingAs($agent)->tool(CreateCustomerTool::class, ['name' => 'dana whitfield'])
        ->assertOk()->assertSee('"created":false')->assertSee('already exists');
    expect(Customer::withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(1);

    ShopServer::actingAs($agent)->tool(CreateCustomerTool::class, ['name' => 'Dana Whitfield', 'allow_duplicate' => true])->assertSee('"created":true');
    expect(Customer::withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(2);

    $entry = Activity::query()->where('subject_type', $customer->getMorphClass())->where('subject_id', $customer->id)->sole();
    expect($entry->causer_id)->toBe($agent->id)->and($entry->log_name)->toBe('mcp')->and($entry->description)->toContain('via AI connection');
});

test('create-customer validates like the web form', function () {
    [, , $agent] = writeShop(['customers.create']);

    ShopServer::actingAs($agent)->tool(CreateCustomerTool::class, ['name' => 'X', 'email' => 'not-an-email'])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(CreateCustomerTool::class, [])->assertHasErrors();
});

test('update-customer changes only what is sent, can clear a field, and refuses an empty change', function () {
    [$shop, , $agent] = writeShop(['customers.update']);
    $customer = Customer::factory()->create(['shop_id' => $shop->id, 'name' => 'Old Name', 'email' => 'old@example.test', 'phone' => '555-1']);

    ShopServer::actingAs($agent)->tool(UpdateCustomerTool::class, ['id' => $customer->id, 'name' => 'New Name'])
        ->assertOk()->assertSee('"updated":true')->assertSee('"changed":["name"]');
    expect($customer->fresh()->only('name', 'email', 'phone'))->toBe(['name' => 'New Name', 'email' => 'old@example.test', 'phone' => '555-1']);

    ShopServer::actingAs($agent)->tool(UpdateCustomerTool::class, ['id' => $customer->id, 'phone' => null])->assertSee('"updated":true');
    expect($customer->fresh()->phone)->toBeNull();

    ShopServer::actingAs($agent)->tool(UpdateCustomerTool::class, ['id' => $customer->id, 'name' => 'New Name'])->assertSee('Nothing to change');
    ShopServer::actingAs($agent)->tool(UpdateCustomerTool::class, ['id' => $customer->id])->assertHasErrors();
});

test('update-customer cannot reach another shop\'s customer', function () {
    [, , $agent] = writeShop(['customers.update']);
    $theirs = Customer::factory()->create(['shop_id' => Shop::factory()->create()->id, 'name' => 'Rival Customer']);

    ShopServer::actingAs($agent)->tool(UpdateCustomerTool::class, ['id' => $theirs->id, 'name' => 'Hijacked'])
        ->assertOk()->assertSee('No customer with that id');

    expect(Customer::withoutGlobalScopes()->find($theirs->id)->name)->toBe('Rival Customer');
});

// --- quotes ------------------------------------------------------------------

test('create-quote makes a draft, takes costs from the catalog and the default markup, and never sends it', function () {
    [$shop, , $agent] = writeShop([...ALL_WRITES]);
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    $plate = Material::factory()->create(['shop_id' => $shop->id, 'name' => 'Plate']);
    $cheap = BusinessPartner::factory()->create(['shop_id' => $shop->id]);
    $dear = BusinessPartner::factory()->create(['shop_id' => $shop->id]);
    $plate->businessPartners()->attach($cheap->id, ['price' => 40, 'dimension' => '4x8']);
    $plate->businessPartners()->attach($dear->id, ['price' => 55, 'dimension' => '4x8']);
    $brake = Equipment::factory()->create(['shop_id' => $shop->id, 'rate' => 75]);

    $response = ShopServer::actingAs($agent)->tool(CreateQuoteTool::class, [
        'customer_id' => $customer->id,
        'notes' => 'Rush job',
        'due_date' => '2026-12-01',
        'line_items' => [
            ['type' => 'material', 'description' => '1/4in plate', 'quantity' => 3, 'material_id' => $plate->id],
            ['type' => 'equipment', 'description' => 'Brake time', 'quantity' => 2, 'equipment_id' => $brake->id],
            ['type' => 'labor', 'description' => 'Welding', 'quantity' => 4],
        ],
    ])->assertOk()->assertSee('"created":true')->assertSee('"status":"draft"');

    $quote = Quote::withoutGlobalScopes()->where('shop_id', $shop->id)->sole();
    $lines = $quote->lineItems()->orderBy('sort_order')->get();

    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->sent_at)->toBeNull()->and($quote->emailed_at)->toBeNull()
        ->and($quote->due_date->toDateString())->toBe('2026-12-01')
        ->and((float) $lines[0]->unit_cost)->toBe(40.0)             // cheapest supplier
        ->and($lines[0]->business_partner_id)->toBe($cheap->id)
        ->and($lines[0]->material_dimension)->toBe('4x8')
        ->and((float) $lines[1]->unit_cost)->toBe(75.0)             // equipment rate
        ->and((float) $lines[2]->unit_cost)->toBe(0.0);             // unlinked: owner prices it

    $response->assertSee('"priced":false'); // the unpriced line is flagged

    $entry = Activity::query()->where('subject_type', $quote->getMorphClass())->where('subject_id', $quote->id)->sole();
    expect($entry->causer_id)->toBe($agent->id);
});

test('create-quote lets an explicit cost override the catalog, like typing over the field', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    $brake = Equipment::factory()->create(['shop_id' => $shop->id, 'rate' => 75]);

    ShopServer::actingAs($agent)->tool(CreateQuoteTool::class, [
        'customer_id' => $customer->id,
        'line_items' => [['type' => 'equipment', 'description' => 'Brake', 'quantity' => 1, 'equipment_id' => $brake->id, 'unit_cost' => 60, 'markup_percent' => 10]],
    ])->assertOk();

    $line = Quote::withoutGlobalScopes()->where('shop_id', $shop->id)->sole()->lineItems()->sole();
    expect((float) $line->unit_cost)->toBe(60.0)->and((float) $line->markup_percent)->toBe(10.0);
});

test('create-quote refuses another shop\'s customer or catalog ids and saves nothing', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $mine = Customer::factory()->create(['shop_id' => $shop->id]);
    $other = Shop::factory()->create();
    $theirCustomer = Customer::factory()->create(['shop_id' => $other->id]);
    $theirMaterial = Material::factory()->create(['shop_id' => $other->id]);

    ShopServer::actingAs($agent)->tool(CreateQuoteTool::class, [
        'customer_id' => $theirCustomer->id,
        'line_items' => [['type' => 'labor', 'description' => 'x', 'quantity' => 1]],
    ])->assertOk()->assertSee('No customer with that id');

    ShopServer::actingAs($agent)->tool(CreateQuoteTool::class, [
        'customer_id' => $mine->id,
        'line_items' => [['type' => 'material', 'description' => 'x', 'quantity' => 1, 'material_id' => $theirMaterial->id]],
    ])->assertHasErrors();

    expect(Quote::withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(0);
});

test('create-quote rejects an employee and a service on the same labor line, and too many lines', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    $service = Service::factory()->create(['shop_id' => $shop->id]);

    ShopServer::actingAs($agent)->tool(CreateQuoteTool::class, [
        'customer_id' => $customer->id,
        'line_items' => [['type' => 'labor', 'description' => 'x', 'quantity' => 1, 'service_id' => $service->id, 'user_id' => $shop->owner_id]],
    ])->assertHasErrors();

    ShopServer::actingAs($agent)->tool(CreateQuoteTool::class, [
        'customer_id' => $customer->id,
        'line_items' => array_fill(0, 51, ['type' => 'labor', 'description' => 'x', 'quantity' => 1]),
    ])->assertHasErrors();

    expect(Quote::withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(0);
});

test('update-quote edits a draft and replaces its lines, but never a sent, won, archived or converted quote', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    $draft = Quote::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'status' => QuoteStatus::Draft]);
    $draft->lineItems()->create(['type' => 'labor', 'description' => 'Old line', 'quantity' => 1, 'unit_cost' => 10, 'markup_percent' => 0, 'sort_order' => 0]);

    ShopServer::actingAs($agent)->tool(UpdateQuoteTool::class, [
        'id' => $draft->id, 'notes' => 'Updated', 'line_items' => [['type' => 'labor', 'description' => 'New line', 'quantity' => 5]],
    ])->assertOk()->assertSee('"updated":true');

    $draft->refresh();
    expect($draft->notes)->toBe('Updated')->and($draft->lineItems()->pluck('description')->all())->toBe(['New line'])
        ->and($draft->status)->toBe(QuoteStatus::Draft);

    loggedOut();
    $sent = sentQuote($shop);
    $won = sentQuote($shop, ['status' => QuoteStatus::Won]);
    $archived = Quote::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'status' => QuoteStatus::Draft, 'archived_at' => now()]);
    $converted = Quote::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'status' => QuoteStatus::Draft]);
    jobForShop($shop)->forceFill(['quote_id' => $converted->id])->save();

    foreach ([$sent, $won, $archived, $converted] as $quote) {
        ShopServer::actingAs($agent)->tool(UpdateQuoteTool::class, ['id' => $quote->id, 'notes' => 'hijack'])
            ->assertOk()->assertSee('"updated":false')->assertSee('Only draft quotes');
        expect($quote->fresh()->notes)->not->toBe('hijack');
    }
});

test('update-quote with nothing to change, or a foreign quote, is refused', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $draft = Quote::factory()->create(['shop_id' => $shop->id, 'customer_id' => Customer::factory()->create(['shop_id' => $shop->id])->id, 'status' => QuoteStatus::Draft]);
    $foreign = sentQuote(Shop::factory()->create(), ['status' => QuoteStatus::Draft, 'sent_at' => null]);

    ShopServer::actingAs($agent)->tool(UpdateQuoteTool::class, ['id' => $draft->id])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(UpdateQuoteTool::class, ['id' => $foreign->id, 'notes' => 'x'])->assertSee('No quote with that id');
});

test('a quote total is only shown to an agent who may see financials', function () {
    [$shop, , $agent] = writeShop(['quotes.create']);
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);

    ShopServer::actingAs($agent)->tool(CreateQuoteTool::class, [
        'customer_id' => $customer->id,
        'line_items' => [['type' => 'labor', 'description' => 'x', 'quantity' => 2, 'unit_cost' => 50, 'markup_percent' => 0]],
    ])->assertOk()->assertDontSee('"total"');
});

// --- jobs --------------------------------------------------------------------

test('update-job sets the due date and assigns by name, logging each change as the agent', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $job = jobForShop($shop);
    $person = User::factory()->create(['name' => 'Jamie Chen', 'current_shop_id' => $shop->id]);
    $shop->members()->attach($person);

    ShopServer::actingAs($agent)->tool(UpdateJobTool::class, ['id' => $job->id, 'due_date' => '2026-12-15', 'assignee' => 'jamie chen'])
        ->assertOk()->assertSee('"updated":true')->assertSee('Due date set to Dec 15, 2026')->assertSee('Assigned to Jamie Chen');

    $job->refresh();
    expect($job->due_date->toDateString())->toBe('2026-12-15')->and($job->assigned_to)->toBe($person->id);

    expect(Activity::query()->where('subject_id', $job->id)->where('subject_type', $job->getMorphClass())->pluck('causer_id')->unique()->all())->toBe([$agent->id]);

    ShopServer::actingAs($agent)->tool(UpdateJobTool::class, ['id' => $job->id, 'due_date' => null, 'assignee' => null])
        ->assertSee('Due date cleared')->assertSee('Unassigned from Jamie Chen');
    expect($job->fresh()->due_date)->toBeNull()->and($job->fresh()->assigned_to)->toBeNull();
});

test('update-job unassigns when the client sends an empty string, and advertises assignee as nullable', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $job = jobForShop($shop);
    $person = User::factory()->create(['name' => 'Jamie Chen', 'current_shop_id' => $shop->id]);
    $shop->members()->attach($person);
    $job->forceFill(['assigned_to' => $person->id])->save();

    ShopServer::actingAs($agent)->tool(UpdateJobTool::class, ['id' => $job->id, 'assignee' => ''])
        ->assertOk()->assertSee('Unassigned from Jamie Chen');
    expect($job->fresh()->assigned_to)->toBeNull();

    $schema = (new UpdateJobTool)->schema(new JsonSchemaTypeFactory)['assignee']->toArray();
    expect(json_encode($schema))->toContain('null');
});

test('update-job refuses an unknown assignee (listing the team), an agent as assignee, and cancelled jobs', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $job = jobForShop($shop);

    ShopServer::actingAs($agent)->tool(UpdateJobTool::class, ['id' => $job->id, 'assignee' => 'Nobody Here'])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(UpdateJobTool::class, ['id' => $job->id, 'assignee' => $agent->name])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(UpdateJobTool::class, ['id' => $job->id])->assertHasErrors();

    $job->forceFill(['cancelled_at' => now()])->save();
    ShopServer::actingAs($agent)->tool(UpdateJobTool::class, ['id' => $job->id, 'due_date' => '2026-12-15'])->assertSee('cancelled');
});

test('move-job moves along the board like a person would, starts the job, and logs the agent', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $job = jobForShop($shop, 'not_started');
    $setup = resolveJobStage($shop, 'in_progress');

    ShopServer::actingAs($agent)->tool(MoveJobTool::class, ['id' => $job->id, 'stage_id' => $setup->id])
        ->assertOk()->assertSee('"moved":true');

    $job->refresh();
    expect($job->job_stage_id)->toBe($setup->id)->and($job->started_at)->not->toBeNull();
    expect(Activity::query()->where('subject_id', $job->id)->where('subject_type', $job->getMorphClass())->sole()->causer_id)->toBe($agent->id);

    // And back again before it ships.
    ShopServer::actingAs($agent)->tool(MoveJobTool::class, ['id' => $job->id, 'stage_id' => resolveJobStage($shop, 'not_started')->id])->assertSee('"moved":true');
    expect($job->fresh()->started_at)->toBeNull();
});

test('move-job can move a job into Shipped and Invoiced, but not touch a cancelled job', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $job = jobForShop($shop);

    ShopServer::actingAs($agent)->tool(MoveJobTool::class, ['id' => $job->id, 'stage_id' => resolveJobStage($shop, 'shipped')->id])
        ->assertOk()->assertSee('"moved":true');
    expect($job->fresh()->job_stage_id)->toBe(resolveJobStage($shop, 'shipped')->id)->and($job->fresh()->finished_at)->not->toBeNull();

    ShopServer::actingAs($agent)->tool(MoveJobTool::class, ['id' => $job->id, 'stage_id' => resolveJobStage($shop, 'invoiced')->id])
        ->assertOk()->assertSee('"moved":true');
    expect($job->fresh()->invoiced_at)->not->toBeNull();

    loggedOut();
    $cancelled = jobForShop($shop);
    $cancelled->forceFill(['cancelled_at' => now()])->save();
    ShopServer::actingAs($agent)->tool(MoveJobTool::class, ['id' => $cancelled->id, 'stage_id' => resolveJobStage($shop, 'in_progress')->id])->assertSee('cancelled');
});

test('move-job respects the board\'s rules: no moving back once shipped, no foreign jobs or stages', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $shipped = jobForShop($shop, 'shipped');
    $foreignStage = resolveJobStage(Shop::factory()->create());

    ShopServer::actingAs($agent)->tool(MoveJobTool::class, ['id' => $shipped->id, 'stage_id' => resolveJobStage($shop, 'not_started')->id])
        ->assertSee('"moved":false')->assertSee("can't move");

    loggedOut();
    $open = jobForShop($shop);
    ShopServer::actingAs($agent)->tool(MoveJobTool::class, ['id' => $open->id, 'stage_id' => $foreignStage->id])->assertSee('No stage with that id');
    ShopServer::actingAs($agent)->tool(MoveJobTool::class, ['id' => 999999, 'stage_id' => resolveJobStage($shop, 'in_progress')->id])->assertSee('No job with that id');
});

test('list-job-stages shows stage ids and roles', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);

    $stages = collect(mcpCall(mcpWriteToken($agent), 'tools/call', ['name' => 'list-job-stages', 'arguments' => (object) []])->json('result.structuredContent.stages'));

    expect($stages->pluck('role')->all())->toContain('not_started', 'shipped', 'invoiced')
        ->and($stages->firstWhere('role', 'shipped'))->toHaveKeys(['id', 'name', 'position']);
});

// --- catalog -----------------------------------------------------------------

test('catalog create tools make records, refuse duplicates, and validate like the forms', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $type = MaterialType::query()->where('shop_id', $shop->id)->first();

    ShopServer::actingAs($agent)->tool(CreateMaterialTool::class, ['name' => 'Rebar', 'unit' => 'ft', 'material_type_id' => $type->id])->assertSee('"created":true');
    ShopServer::actingAs($agent)->tool(CreateMaterialTool::class, ['name' => 'REBAR'])->assertSee('"created":false')->assertSee('already exists');
    ShopServer::actingAs($agent)->tool(CreateServiceTool::class, ['name' => 'Galvanizing'])->assertSee('"created":true');
    ShopServer::actingAs($agent)->tool(CreateEquipmentTool::class, ['name' => 'Laser', 'billing_method' => 'hourly', 'rate' => 120])->assertSee('"created":true');
    ShopServer::actingAs($agent)->tool(CreateToolTool::class, ['name' => 'Hole saw', 'cuts' => [$type->id], 'cost' => 2.5, 'unit' => 'hole'])->assertSee('"created":true');

    expect(Material::withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(1)
        ->and(Tool::withoutGlobalScopes()->where('shop_id', $shop->id)->sole()->materialTypes()->pluck('material_types.id')->all())->toBe([$type->id]);

    ShopServer::actingAs($agent)->tool(CreateEquipmentTool::class, ['name' => 'X', 'billing_method' => 'weekly', 'rate' => 1])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(CreateEquipmentTool::class, ['name' => 'X', 'billing_method' => 'flat', 'rate' => -5])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(CreateToolTool::class, ['name' => 'No cuts', 'cuts' => [], 'cost' => 1, 'unit' => 'each'])->assertHasErrors();
});

test('catalog tools refuse another shop\'s material types and materials', function () {
    [, , $agent] = writeShop(ALL_WRITES);
    $theirType = MaterialType::query()->where('shop_id', Shop::factory()->create()->id)->first();

    ShopServer::actingAs($agent)->tool(CreateMaterialTool::class, ['name' => 'Sneaky', 'material_type_id' => $theirType->id])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(CreateToolTool::class, ['name' => 'Sneaky', 'cuts' => [$theirType->id], 'cost' => 1, 'unit' => 'each'])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(CreateBusinessPartnerTool::class, ['name' => 'Sneaky', 'materials' => [['material_id' => Material::factory()->create(['shop_id' => $theirType->shop_id])->id, 'price' => 1]]])->assertHasErrors();

    expect(Material::withoutGlobalScopes()->where('name', 'Sneaky')->count())->toBe(0)
        ->and(BusinessPartner::withoutGlobalScopes()->where('name', 'Sneaky')->count())->toBe(0);
});

test('catalog update tools change only what is sent', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $material = Material::factory()->create(['shop_id' => $shop->id, 'name' => 'Plate', 'unit' => 'sheet', 'notes' => 'keep me']);
    $service = Service::factory()->create(['shop_id' => $shop->id, 'name' => 'Coating']);
    $equipment = Equipment::factory()->create(['shop_id' => $shop->id, 'name' => 'Brake', 'rate' => 50, 'billing_method' => 'hourly']);

    ShopServer::actingAs($agent)->tool(UpdateMaterialTool::class, ['id' => $material->id, 'unit' => 'ft'])->assertSee('"updated":true');
    expect($material->fresh()->only('name', 'unit', 'notes'))->toBe(['name' => 'Plate', 'unit' => 'ft', 'notes' => 'keep me']);

    ShopServer::actingAs($agent)->tool(UpdateServiceTool::class, ['id' => $service->id, 'name' => 'Powder coating'])->assertSee('"updated":true');
    ShopServer::actingAs($agent)->tool(UpdateEquipmentTool::class, ['id' => $equipment->id, 'rate' => 65])->assertSee('"updated":true');
    expect((float) $equipment->fresh()->rate)->toBe(65.0)->and($equipment->fresh()->name)->toBe('Brake');

    ShopServer::actingAs($agent)->tool(UpdateMaterialTool::class, ['id' => $material->id])->assertHasErrors();
    ShopServer::actingAs($agent)->tool(UpdateMaterialTool::class, ['id' => 999999, 'unit' => 'x'])->assertSee('No material with that id');
});

test('update-tool replaces its material types only when cuts is sent', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    [$a, $b] = MaterialType::query()->where('shop_id', $shop->id)->limit(2)->get();
    $tool = Tool::factory()->create(['shop_id' => $shop->id, 'name' => 'Bit', 'cost' => 5]);
    $tool->materialTypes()->sync([$a->id]);

    ShopServer::actingAs($agent)->tool(UpdateToolTool::class, ['id' => $tool->id, 'cost' => 6])->assertSee('"updated":true');
    expect($tool->materialTypes()->pluck('material_types.id')->all())->toBe([$a->id]);

    ShopServer::actingAs($agent)->tool(UpdateToolTool::class, ['id' => $tool->id, 'cuts' => [$b->id]])->assertSee('"changed":["cuts"]');
    expect($tool->fresh()->materialTypes()->pluck('material_types.id')->all())->toBe([$b->id]);

    ShopServer::actingAs($agent)->tool(UpdateToolTool::class, ['id' => $tool->id, 'cuts' => []])->assertHasErrors();
});

test('business partners carry the supplier prices, and a quote picks them up', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $plate = Material::factory()->create(['shop_id' => $shop->id]);
    $coating = Service::factory()->create(['shop_id' => $shop->id]);
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);

    ShopServer::actingAs($agent)->tool(CreateBusinessPartnerTool::class, [
        'name' => 'Coastal Metal Supply',
        'email' => 'sales@coastal.test',
        'materials' => [['material_id' => $plate->id, 'price' => 48.5, 'dimension' => '4x8'], ['material_id' => $plate->id, 'price' => 90, 'dimension' => '5x10']],
        'services' => [['service_id' => $coating->id, 'price' => 22]],
    ])->assertOk()->assertSee('"created":true');

    $partner = BusinessPartner::withoutGlobalScopes()->where('shop_id', $shop->id)->sole();
    expect($partner->materials()->count())->toBe(2)->and($partner->services()->count())->toBe(1);

    ShopServer::actingAs($agent)->tool(CreateQuoteTool::class, [
        'customer_id' => $customer->id,
        'line_items' => [['type' => 'material', 'description' => 'Plate', 'quantity' => 1, 'material_id' => $plate->id]],
    ])->assertOk();
    expect((float) Quote::withoutGlobalScopes()->where('shop_id', $shop->id)->sole()->lineItems()->sole()->unit_cost)->toBe(48.5);
});

test('update-business-partner changes details without touching price lists unless they are sent', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $plate = Material::factory()->create(['shop_id' => $shop->id]);
    $partner = BusinessPartner::factory()->create(['shop_id' => $shop->id, 'name' => 'Supplier', 'phone' => '555-1']);
    $partner->materials()->attach($plate->id, ['price' => 10, 'dimension' => null]);

    ShopServer::actingAs($agent)->tool(UpdateBusinessPartnerTool::class, ['id' => $partner->id, 'phone' => '555-2'])->assertSee('"changed":["phone"]');
    expect($partner->fresh()->phone)->toBe('555-2')->and($partner->materials()->count())->toBe(1);

    ShopServer::actingAs($agent)->tool(UpdateBusinessPartnerTool::class, ['id' => $partner->id, 'materials' => [['material_id' => $plate->id, 'price' => 12]]])->assertSee('"changed":["materials"]');
    expect((float) $partner->materials()->first()->pivot->price)->toBe(12.0);

    ShopServer::actingAs($agent)->tool(UpdateBusinessPartnerTool::class, ['id' => $partner->id, 'materials' => []])->assertSee('"changed":["materials"]');
    expect($partner->materials()->count())->toBe(0);

    ShopServer::actingAs($agent)->tool(UpdateBusinessPartnerTool::class, ['id' => $partner->id, 'materials' => [['price' => 1]]])->assertHasErrors();
});

// --- search-catalog ----------------------------------------------------------

test('search-catalog returns what the agent may see, and says so when it may not', function () {
    [$shop, , $agent] = writeShop(['materials.view']);
    $plate = Material::factory()->create(['shop_id' => $shop->id, 'name' => 'Steel plate']);
    $partner = BusinessPartner::factory()->create(['shop_id' => $shop->id, 'name' => 'Secret Supplier']);
    $plate->businessPartners()->attach($partner->id, ['price' => 33, 'dimension' => null]);
    Equipment::factory()->create(['shop_id' => $shop->id, 'name' => 'Laser']);

    ShopServer::actingAs($agent)->tool(SearchCatalogTool::class, ['type' => 'material', 'query' => 'plate'])
        ->assertOk()->assertSee('Steel plate')->assertDontSee('Secret Supplier')->assertDontSee('"price"');

    ShopServer::actingAs($agent)->tool(SearchCatalogTool::class, ['type' => 'equipment'])
        ->assertOk()->assertDontSee('Laser')->assertSee('has not been given access');

    ShopServer::actingAs($agent)->tool(SearchCatalogTool::class, ['type' => 'material-type'])->assertOk()->assertSee('Carbon steel');
});

test('supplier prices appear in search-catalog only with business-partners access', function () {
    [$shop, , $agent] = writeShop(['materials.view', 'business-partners.view']);
    $plate = Material::factory()->create(['shop_id' => $shop->id, 'name' => 'Steel plate']);
    $partner = BusinessPartner::factory()->create(['shop_id' => $shop->id, 'name' => 'Coastal']);
    $plate->businessPartners()->attach($partner->id, ['price' => 33, 'dimension' => null]);

    ShopServer::actingAs($agent)->tool(SearchCatalogTool::class, ['type' => 'material'])->assertSee('Coastal')->assertSee('"price":33');
});

test('search-catalog only ever sees the agent\'s own shop', function () {
    [, , $agent] = writeShop(['materials.view']);
    Material::factory()->create(['shop_id' => Shop::factory()->create()->id, 'name' => 'Rival plate']);

    ShopServer::actingAs($agent)->tool(SearchCatalogTool::class, ['type' => 'material'])->assertOk()->assertDontSee('Rival plate');
});

// --- end to end --------------------------------------------------------------

test('a write made over the real endpoint with a real token works and is attributed', function () {
    [$shop, , $agent] = writeShop(['customers.create']);

    $response = mcpCall(mcpWriteToken($agent), 'tools/call', ['name' => 'create-customer', 'arguments' => ['name' => 'Wire Customer']])->assertOk();

    expect($response->json('result.isError') ?? false)->toBeFalse();
    expect(Customer::withoutGlobalScopes()->where('shop_id', $shop->id)->where('name', 'Wire Customer')->exists())->toBeTrue()
        ->and(McpRequest::withoutGlobalScopes()->where('tool', 'create-customer')->sole()->status)->toBe('ok');
});

test('calling a write tool without its permission over the wire is refused and writes nothing', function () {
    [$shop, , $agent] = writeShop(['customers.view']);

    mcpCall(mcpWriteToken($agent), 'tools/call', ['name' => 'create-customer', 'arguments' => ['name' => 'Nope']]);

    expect(Customer::withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(0);
});

test('when ai access lapses even a fully-granted agent can write nothing', function () {
    [$shop, , $agent] = writeShop(ALL_WRITES);
    $token = mcpWriteToken($agent);
    $shop->update(['plan_id' => Plan::where('key', 'free')->value('id')]);

    mcpCall($token, 'tools/call', ['name' => 'create-customer', 'arguments' => ['name' => 'Too late']])->assertForbidden();

    expect(Customer::withoutGlobalScopes()->where('name', 'Too late')->count())->toBe(0);
});

test('write tools are covered by the per-minute limit', function () {
    [, , $agent] = writeShop(['customers.create']);
    config(['ai.mcp_requests_per_minute' => 3]);
    $token = mcpWriteToken($agent);

    foreach (range(1, 3) as $i) {
        mcpCall($token, 'tools/call', ['name' => 'create-customer', 'arguments' => ['name' => "Rate {$i}"]])->assertOk();
    }

    mcpCall($token, 'tools/call', ['name' => 'create-customer', 'arguments' => ['name' => 'Rate 4']])->assertStatus(429);
});
