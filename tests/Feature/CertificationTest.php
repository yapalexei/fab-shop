<?php

use App\Models\Certification;
use App\Models\Plan;
use App\Models\Procedure;
use App\Models\Shop;
use App\Models\User;

test('a certification can be added, edited, and deleted for a team member', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $procedure = Procedure::factory()->for($shop)->create();

    $this->actingAs($user)->post(route('certifications.store'), [
        'user_id' => $user->id, 'procedure_id' => $procedure->id,
        'cert_number' => 'AWS-123', 'issuing_body' => 'AWS',
        'issued_at' => '2025-01-01', 'expires_at' => '2027-01-01',
    ])->assertRedirect(route('certifications.index'));

    $certification = Certification::sole();
    expect($certification->shop_id)->toBe($shop->id)
        ->and($certification->cert_number)->toBe('AWS-123');

    $this->actingAs($user)->put(route('certifications.update', $certification), [
        'user_id' => $user->id, 'procedure_id' => $procedure->id,
        'cert_number' => 'AWS-999',
    ])->assertRedirect(route('certifications.index'));
    expect($certification->fresh()->cert_number)->toBe('AWS-999');

    $this->actingAs($user)->delete(route('certifications.destroy', $certification))
        ->assertRedirect(route('certifications.index'));
    expect(Certification::count())->toBe(0);
});

test('a certification is not required to reference a procedure', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('certifications.store'), [
        'user_id' => $user->id, 'issuing_body' => 'OSHA', 'cert_number' => 'OSHA-10',
    ])->assertSessionHasNoErrors();

    expect(Certification::sole()->procedure_id)->toBeNull();
});

test('a certification cannot be issued to a user outside the shop', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $outsider = User::factory()->create();

    $this->actingAs($user)->post(route('certifications.store'), [
        'user_id' => $outsider->id,
    ])->assertSessionHasErrors('user_id');
});

test('a certification cannot reference another shop\'s procedure', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $foreignProcedure = Procedure::factory()->for(Shop::factory()->create())->create();

    $this->actingAs($user)->post(route('certifications.store'), [
        'user_id' => $user->id, 'procedure_id' => $foreignProcedure->id,
    ])->assertSessionHasErrors('procedure_id');
});

test('expiry is flagged on the certifications list', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $expired = Certification::factory()->for($shop)->expired()->create(['user_id' => $user->id]);
    $current = Certification::factory()->for($shop)->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('certifications.index'))
        ->assertInertia(fn ($page) => $page
            ->where('certifications', fn ($rows) => collect($rows)->firstWhere('id', $expired->id)['is_expired'] === true
                && collect($rows)->firstWhere('id', $current->id)['is_expired'] === false));
});

test('a user cannot see or manage another shop\'s certifications', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $foreignCertification = Certification::factory()->for(Shop::factory()->create())->create();

    $this->actingAs($user)->get(route('certifications.edit', $foreignCertification))->assertNotFound();
    $this->actingAs($user)->delete(route('certifications.destroy', $foreignCertification))->assertNotFound();
});

test('staff can view but not create or delete certifications', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $certification = Certification::factory()->for($shop)->create();

    $this->actingAs($staff)->get(route('certifications.index'))->assertOk();
    $this->actingAs($staff)->post(route('certifications.store'), ['user_id' => $staff->id])->assertForbidden();
    $this->actingAs($staff)->delete(route('certifications.destroy', $certification))->assertForbidden();
});

test('certifications are read-only for a shop without the qualification-records feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('certifications.index'))->assertOk();
    $this->actingAs($user)->post(route('certifications.store'), [])->assertForbidden();
});
