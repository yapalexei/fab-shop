<?php

use App\Enums\Feature;
use App\Models\Certification;
use App\Models\Plan;
use App\Models\Procedure;
use App\Models\Shop;

test('a procedure can be added, edited, and deleted', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('procedures.store'), [
        'name' => 'GMAW Steel 3G', 'code' => 'WPS-014',
    ])->assertRedirect(route('procedures.index'));

    $procedure = Procedure::sole();
    expect($procedure->shop_id)->toBe($shop->id)
        ->and($procedure->code)->toBe('WPS-014');

    $this->actingAs($user)->put(route('procedures.update', $procedure), [
        'name' => 'GMAW Steel 3G', 'code' => 'WPS-014-R1',
    ])->assertRedirect(route('procedures.index'));
    expect($procedure->fresh()->code)->toBe('WPS-014-R1');

    $this->actingAs($user)->delete(route('procedures.destroy', $procedure))
        ->assertRedirect(route('procedures.index'));
    expect(Procedure::count())->toBe(0);
});

test('procedure names are unique per shop, case-insensitively', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    Procedure::factory()->for($shop)->create(['name' => 'Forklift Operation']);

    $this->actingAs($user)->post(route('procedures.store'), [
        'name' => 'forklift operation',
    ])->assertSessionHasErrors('name');
});

test('the same procedure name can exist in two different shops', function () {
    Procedure::factory()->for(Shop::factory()->create())->create(['name' => 'Forklift Operation']);
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('procedures.store'), [
        'name' => 'Forklift Operation',
    ])->assertSessionHasNoErrors();
});

test('a procedure still used by a certification cannot be deleted', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $procedure = Procedure::factory()->for($shop)->create();
    Certification::factory()->for($shop)->create(['procedure_id' => $procedure->id]);

    $this->actingAs($user)->delete(route('procedures.destroy', $procedure))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Procedure::count())->toBe(1);
});

test('a user cannot see or manage another shop\'s procedures', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $foreignProcedure = Procedure::factory()->for(Shop::factory()->create())->create();

    $this->actingAs($user)->get(route('procedures.edit', $foreignProcedure))->assertNotFound();
    $this->actingAs($user)->delete(route('procedures.destroy', $foreignProcedure))->assertNotFound();
});

test('staff can view but not create or delete procedures', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $procedure = Procedure::factory()->for($shop)->create();

    $this->actingAs($staff)->get(route('procedures.index'))->assertOk();
    $this->actingAs($staff)->post(route('procedures.store'), ['name' => 'x'])->assertForbidden();
    $this->actingAs($staff)->delete(route('procedures.destroy', $procedure))->assertForbidden();
});

test('procedures are read-only for a shop without the qualification-records feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('procedures.index'))->assertOk();
    $this->actingAs($user)->post(route('procedures.store'), [])->assertForbidden();
});

test('the qualification-records feature is flagged as shipped', function () {
    expect(Feature::QualificationRecords->isShipped())->toBeTrue();
});
