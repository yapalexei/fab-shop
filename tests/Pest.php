<?php

use App\Models\Shop;
use App\Models\User;
use App\Support\ShopPermissions;
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
 * A platform staff user (App\Models\User::$is_platform_admin) - a separate
 * trust boundary from any per-shop role, so this deliberately doesn't go
 * through actingAsShopMember.
 */
function actingAsPlatformAdmin(): User
{
    return User::factory()->create(['is_platform_admin' => true]);
}
