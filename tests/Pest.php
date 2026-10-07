<?php

use App\Actions\CreateShopAgent;
use App\Enums\JobStageRole;
use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Shop;
use App\Models\User;
use App\Support\AgentAccess;
use App\Support\ShopPermissions;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // RefreshDatabase resets tables via transactions, which bypasses the
        // model events Spatie relies on to invalidate its role/permission
        // cache - without this, a role assigned in one test can leak into
        // the next since the array cache store persists for the whole
        // process, not per test.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A member of $shop acting within it, holding $role there. Defaults to
 * Owner (full access) - tests about restricted access pass 'Admin'/'Staff'.
 * The shop's roles get their real default grants, same as signup.
 */
function actingAsShopMember(Shop $shop, string $role = 'Owner'): User
{
    $user = User::factory()->create(['current_shop_id' => $shop->id]);
    $shop->members()->attach($user);

    ShopPermissions::seedRoles($shop);
    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
    $user->assignRole(Role::findOrCreate($role));

    return $user;
}

/**
 * Alias kept for readability where the role is the point of the test.
 */
function actingAsShopRole(Shop $shop, string $role): User
{
    return actingAsShopMember($shop, $role);
}

/**
 * Job::factory()->for($shop) only overrides shop_id - its definition()
 * independently creates a Quote+Customer (and, via JobStage::whereNull, a
 * job_stage_id) under their own Shop, so those relations would resolve to
 * nothing (or the wrong shop's stage) once the tenant scope kicks in unless
 * we build them under the same shop explicitly. Shared across the
 * Job-related test files.
 *
 * $stageKey is one of 'not_started' (default), 'shipped', 'invoiced', or
 * omitted/anything else for the shop's generic custom stage ("Setup") -
 * standing in for "some mid-pipeline stage" the way JobStatus::Cutting used
 * to before stages became shop-configurable.
 */
function jobForShop(Shop $shop, ?string $stageKey = null): Job
{
    $customer = Customer::factory()->for($shop)->create();
    $quote = Quote::factory()->for($shop)->create(['customer_id' => $customer->id]);

    return Job::factory()->for($shop)->create([
        'quote_id' => $quote->id,
        'customer_id' => $customer->id,
        'job_stage_id' => resolveJobStage($shop, $stageKey)->id,
    ]);
}

function resolveJobStage(Shop $shop, ?string $stageKey = null): JobStage
{
    $query = JobStage::query()->where('shop_id', $shop->id);

    return match ($stageKey) {
        'shipped' => (clone $query)->where('role', JobStageRole::Shipped)->firstOrFail(),
        'invoiced' => (clone $query)->where('role', JobStageRole::Invoiced)->firstOrFail(),
        'not_started', null => (clone $query)->where('role', JobStageRole::NotStarted)->firstOrFail(),
        default => (clone $query)->whereNull('role')->firstOrFail(),
    };
}

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

/**
 * A platform staff user (App\Models\User::$is_platform_admin) - a separate
 * trust boundary from any per-shop role, so this deliberately doesn't go
 * through actingAsShopMember.
 */
function actingAsPlatformAdmin(): User
{
    return User::factory()->create(['is_platform_admin' => true]);
}
