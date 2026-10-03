<?php

use App\Enums\Feature;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Quote;
use App\Models\Shop;
use App\Models\User;
use App\Support\ShopPermissions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * @param  array<string, bool|null>  $overrides
 * @return array<int, array{permission: string, value: bool|null}>
 */
function overridePayload(array $overrides): array
{
    return collect($overrides)->map(fn ($value, $permission) => ['permission' => $permission, 'value' => $value])->values()->all();
}

function roleIn(Shop $shop, string $role): Role
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);

    return Role::findOrCreate($role);
}

function asCurrentShop(User $user, Shop $shop): User
{
    $user->current_shop_id = $shop->id;
    $user->save();
    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);

    return $user->fresh();
}

// --- Resolution ----------------------------------------------------------

test('the owner holds every permission; admin and staff start from their role defaults', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $admin = actingAsShopMember($shop, 'Admin');
    $staff = actingAsShopMember($shop, 'Staff');

    expect($owner->shopPermissions())->toEqualCanonicalizing(ShopPermissions::all())
        ->and($admin->hasShopPermission('quotes.delete'))->toBeTrue()
        ->and($admin->hasShopPermission('team.manage-access'))->toBeTrue()
        ->and($admin->hasShopPermission('financials.view'))->toBeFalse()
        ->and($admin->hasShopPermission('quickbooks.delete'))->toBeFalse()
        ->and($staff->hasShopPermission('quotes.create'))->toBeTrue()
        ->and($staff->hasShopPermission('jobs.update'))->toBeTrue()
        ->and($staff->hasShopPermission('quotes.delete'))->toBeFalse()
        ->and($staff->hasShopPermission('jobs.cancel'))->toBeFalse()
        ->and($staff->hasShopPermission('materials.create'))->toBeFalse();
});

test('the owner can change what a role is granted', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($owner)->put(route('shop.permissions.update'), [
        'grants' => ['Admin' => ShopPermissions::defaultsFor('Admin'), 'Staff' => ['jobs.view', 'jobs.cancel']],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $fresh = $staff->fresh();
    expect($fresh->hasShopPermission('jobs.cancel'))->toBeTrue()
        ->and($fresh->hasShopPermission('quotes.create'))->toBeFalse();
});

test('role grants in one shop never leak into another', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $user = actingAsShopMember($shopA, 'Staff');
    $shopB->members()->attach($user);
    ShopPermissions::seedRoles($shopB);
    roleIn($shopB, 'Staff');
    $user->assignRole('Staff');

    roleIn($shopA, 'Staff')->givePermissionTo('quotes.delete');

    expect(asCurrentShop($user, $shopA)->hasShopPermission('quotes.delete'))->toBeTrue()
        ->and(asCurrentShop($user, $shopB)->hasShopPermission('quotes.delete'))->toBeFalse();
});

test('a member override can allow what the role lacks, and deny what it grants', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($owner)->put(route('team.access.update', $staff), [
        'role' => 'Staff',
        'overrides' => overridePayload(['financials.view' => true, 'quotes.create' => false, 'jobs.update' => null]),
    ])->assertRedirect(route('team.access.edit', $staff))->assertSessionHasNoErrors();

    $fresh = $staff->fresh();
    expect($fresh->hasShopPermission('financials.view'))->toBeTrue()
        ->and($fresh->hasShopPermission('quotes.create'))->toBeFalse()
        ->and($fresh->hasShopPermission('jobs.update'))->toBeTrue()
        ->and($shop->members()->find($staff->id)->pivot->permission_overrides)
        ->toEqual(['financials.view' => true, 'quotes.create' => false]);
});

test('a member override in one shop does not apply in another shop they belong to', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $ownerA = actingAsShopMember($shopA, 'Owner');
    $user = actingAsShopMember($shopA, 'Staff');
    $shopB->members()->attach($user);
    ShopPermissions::seedRoles($shopB);
    roleIn($shopB, 'Staff');
    $user->assignRole('Staff');

    $this->actingAs($ownerA)->put(route('team.access.update', $user), [
        'role' => 'Staff',
        'overrides' => overridePayload(['financials.view' => true]),
    ]);

    expect(asCurrentShop($user, $shopB)->hasShopPermission('financials.view'))->toBeFalse();
});

test('any action on a resource implies being able to see it', function () {
    $shop = Shop::factory()->create();
    roleIn($shop, 'Staff');
    $staff = actingAsShopMember($shop, 'Staff');
    roleIn($shop, 'Staff')->revokePermissionTo('materials.view');
    $shop->members()->updateExistingPivot($staff->id, ['permission_overrides' => ['materials.update' => true]]);

    expect($staff->fresh()->hasShopPermission('materials.view'))->toBeTrue();
});

// --- Who can change access -----------------------------------------------

test('the owner can change a member\'s role, but nobody becomes a second owner', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($owner)->put(route('team.access.update', $staff), ['role' => 'Admin', 'overrides' => []])
        ->assertSessionHasNoErrors();
    roleIn($shop, 'Admin');
    expect($staff->fresh()->getRoleNames()->all())->toBe(['Admin']);

    $this->actingAs($owner)->put(route('team.access.update', $staff), ['role' => 'Owner', 'overrides' => []])
        ->assertSessionHasErrors('role');
});

test('nobody can edit the owner\'s access or their own', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $admin = actingAsShopMember($shop, 'Admin');

    $this->actingAs($admin)->get(route('team.access.edit', $owner))->assertForbidden();
    $this->actingAs($admin)->get(route('team.access.edit', $admin))->assertForbidden();
});

test('an admin can adjust a staff member\'s access within their own permissions', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($admin)->put(route('team.access.update', $staff), [
        'role' => 'Staff',
        'overrides' => overridePayload(['quotes.delete' => true, 'quotes.create' => false]),
    ])->assertSessionHasNoErrors();

    expect($staff->fresh()->hasShopPermission('quotes.delete'))->toBeTrue();
});

test('an admin cannot give a staff member access they do not have themselves', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($admin)->put(route('team.access.update', $staff), [
        'role' => 'Staff',
        'overrides' => overridePayload(['financials.view' => true]),
    ])->assertSessionHasErrors('overrides.0.value');

    expect($staff->fresh()->hasShopPermission('financials.view'))->toBeFalse();
});

test('an admin can re-save a member whose owner-granted access exceeds their own', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');
    $staff = actingAsShopMember($shop, 'Staff');
    $shop->members()->updateExistingPivot($staff->id, ['permission_overrides' => ['financials.view' => true]]);

    $this->actingAs($admin)->put(route('team.access.update', $staff), [
        'role' => 'Staff',
        'overrides' => overridePayload(['financials.view' => true, 'quotes.create' => false]),
    ])->assertSessionHasNoErrors();
});

test('an admin cannot change roles or touch another admin\'s access', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');
    $otherAdmin = actingAsShopMember($shop, 'Admin');
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($admin)->put(route('team.access.update', $staff), ['role' => 'Admin', 'overrides' => []])
        ->assertSessionHasErrors('role');
    $this->actingAs($admin)->get(route('team.access.edit', $otherAdmin))->assertForbidden();
});

test('staff cannot change anyone\'s access, and only the owner edits role defaults', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');
    $staff = actingAsShopMember($shop, 'Staff');
    $otherStaff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($staff)->get(route('team.access.edit', $otherStaff))->assertForbidden();
    $this->actingAs($admin)->get(route('shop.permissions.edit'))->assertForbidden();
    $this->actingAs($admin)->put(route('shop.permissions.update'), ['grants' => ['Admin' => ['financials.view']]])->assertForbidden();
});

test('access cannot be edited for someone outside the current shop', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $outsider = actingAsShopMember(Shop::factory()->create(), 'Staff');

    $this->actingAs($owner)->get(route('team.access.edit', $outsider))->assertNotFound();
    $this->actingAs($owner)->put(route('team.access.update', $outsider), ['role' => 'Admin', 'overrides' => []])->assertNotFound();
});

test('unknown permission names are rejected', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($owner)->put(route('shop.permissions.update'), ['grants' => ['Staff' => ['delete-everything']]])
        ->assertSessionHasErrors('grants.Staff.0');
    $this->actingAs($owner)->put(route('team.access.update', $staff), [
        'role' => 'Staff', 'overrides' => overridePayload(['delete-everything' => true]),
    ])->assertSessionHasErrors('overrides.0.permission');
});

test('effective permissions are shared with every page', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($staff)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.isOwner', false)
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all()
                === collect(ShopPermissions::defaultsFor('Staff'))->sort()->values()->all()));
});

// --- Enforcement on real routes ------------------------------------------

test('staff defaults: quote and move jobs, read-only catalog, no deletes or cancellations', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $customer = Customer::factory()->for($shop)->create();
    $material = Material::factory()->for($shop)->create();
    $job = jobForShop($shop, 'in_progress');
    $shipped = resolveJobStage($shop, 'shipped');

    $this->actingAs($staff)->get(route('customers.create'))->assertOk();
    $this->actingAs($staff)->delete(route('customers.destroy', $customer))->assertForbidden();

    $this->actingAs($staff)->get(route('materials.index'))->assertOk();
    $this->actingAs($staff)->get(route('materials.edit', $material))->assertOk();
    $this->actingAs($staff)->put(route('materials.update', $material), ['name' => 'X', 'unit' => 'ft'])->assertForbidden();
    $this->actingAs($staff)->post(route('materials.store'), ['name' => 'X', 'unit' => 'ft'])->assertForbidden();

    $this->actingAs($staff)->patch(route('jobs.status', $job), ['job_stage_id' => $shipped->id])->assertRedirect();
    $this->actingAs($staff)->patch(route('jobs.status', $job), ['cancel' => true])->assertForbidden();
    expect($job->fresh()->job_stage_id)->toBe($shipped->id);

    $this->actingAs($staff)->get(route('shop.settings.edit'))->assertForbidden();
});

test('staff are only offered the job moves they are allowed to make', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $job = jobForShop($shop, 'in_progress');

    $this->actingAs($staff)->get(route('jobs.show', $job))
        ->assertInertia(fn ($page) => $page->where('job.available_transitions', fn ($transitions) => ! collect($transitions)->contains('value', 'cancelled')));
});

test('staff can convert a won quote into a job by default', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $quote = Quote::factory()->for($shop)->create([
        'customer_id' => Customer::factory()->for($shop)->create()->id,
        'status' => 'won',
    ]);

    $this->actingAs($staff)->post(route('quotes.convert', $quote))->assertRedirect();
});

test('converting a won quote into a job needs jobs.create', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $shop->members()->updateExistingPivot($staff->id, ['permission_overrides' => ['jobs.create' => false]]);
    $quote = Quote::factory()->for($shop)->create([
        'customer_id' => Customer::factory()->for($shop)->create()->id,
        'status' => 'won',
    ]);

    $this->actingAs($staff->fresh())->post(route('quotes.convert', $quote))->assertForbidden();
});

test('a denied view override locks a member out of that section entirely', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $shop->members()->updateExistingPivot($staff->id, ['permission_overrides' => ['customers.view' => false, 'customers.create' => false, 'customers.update' => false]]);

    $this->actingAs($staff->fresh())->get(route('customers.index'))->assertForbidden();
});

test('an admin can connect quickbooks but only the owner can disconnect it by default', function () {
    if (! Feature::QuickbooksSync->isShipped()) {
        $this->markTestSkipped('QuickBooks sync is switched off (future feature) - see Feature::isShipped().');
    }

    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');

    $this->actingAs($admin)->get(route('quickbooks.show'))->assertOk();
    $this->actingAs($admin)->delete(route('quickbooks.destroy'))->assertForbidden();
});

// --- Data integrity on delete --------------------------------------------

test('a customer with quotes cannot be deleted - the reason is shown instead of an error page', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $customer = Customer::factory()->for($shop)->create();
    Quote::factory()->for($shop)->create(['customer_id' => $customer->id]);

    $this->actingAs($owner)->from(route('customers.index'))->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'))
        ->assertSessionHas('error');

    expect(Customer::withoutGlobalScopes()->find($customer->id))->not->toBeNull();
});

test('a quote that became a job cannot be deleted', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $job = jobForShop($shop);

    $this->actingAs($owner)->from(route('quotes.index'))->delete(route('quotes.destroy', $job->quote))
        ->assertSessionHas('error');

    expect(Quote::withoutGlobalScopes()->find($job->quote_id))->not->toBeNull();
});
