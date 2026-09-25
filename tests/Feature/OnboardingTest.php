<?php

use App\Models\BusinessPartner;
use App\Models\Invitation;
use App\Models\Material;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PlanSeeder;

test('a verified user with no shop is sent to onboarding from any authenticated route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding.show'));
    $this->actingAs($user)->get(route('customers.index'))->assertRedirect(route('onboarding.show'));
});

test('an unverified user is sent to verify their email, not onboarding', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('a member of an already-onboarded shop is never sent to onboarding', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('onboarding step 1 creates the shop, attaches the owner, and seeds role defaults', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('onboarding.show'))
        ->assertInertia(fn ($page) => $page->component('Onboarding/ShopStep'));

    $this->actingAs($user)->post(route('onboarding.shop'), [
        'name' => 'Ironclad Fabrication', 'timezone' => 'America/Denver', 'plan' => 'start-up',
    ])->assertRedirect(route('onboarding.show'));

    $shop = $user->fresh()->currentShop;

    expect($shop->name)->toBe('Ironclad Fabrication')
        ->and($shop->timezone)->toBe('America/Denver')
        ->and($shop->plan->key)->toBe('start-up')
        ->and($shop->onboarding_step)->toBe('team')
        ->and($shop->isOnboarded())->toBeFalse()
        ->and($shop->onTrial())->toBeTrue()
        ->and($user->fresh()->hasRole('Owner'))->toBeTrue();
});

test('an invalid plan key is rejected', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('onboarding.shop'), [
        'name' => 'X', 'plan' => 'enterprise',
    ])->assertSessionHasErrors('plan');

    expect($user->fresh()->currentShop)->toBeNull();
});

test('resubmitting step 1 updates the same shop rather than creating a second one', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('onboarding.shop'), ['name' => 'First', 'plan' => 'free']);
    $firstShopId = $user->fresh()->current_shop_id;

    $this->actingAs($user->fresh())->post(route('onboarding.shop'), ['name' => 'Renamed', 'plan' => 'pro']);

    expect(Shop::count())->toBe(1)
        ->and($user->fresh()->current_shop_id)->toBe($firstShopId)
        ->and($user->fresh()->currentShop->name)->toBe('Renamed');
});

test('after step 1, onboarding shows the team step', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('onboarding.shop'), ['name' => 'X', 'plan' => 'free']);

    $this->actingAs($user->fresh())->get(route('onboarding.show'))
        ->assertInertia(fn ($page) => $page->component('Onboarding/TeamStep')->where('shopName', 'X'));
});

test('the team step sends invitations and blank rows are dropped, not rejected', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('onboarding.shop'), ['name' => 'X', 'plan' => 'free']);
    $shop = $user->fresh()->currentShop;

    $this->actingAs($user->fresh())->post(route('onboarding.team'), [
        'invitations' => [
            ['email' => 'staff@example.com', 'role' => 'Staff'],
            ['email' => '', 'role' => 'Staff'],
        ],
    ])->assertRedirect(route('onboarding.show'))->assertSessionHasNoErrors();

    expect(Invitation::where('shop_id', $shop->id)->count())->toBe(1)
        ->and($shop->fresh()->onboarding_step)->toBe('catalog');
});

test('skipping the team step sends no invitations and still advances', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('onboarding.shop'), ['name' => 'X', 'plan' => 'free']);
    $shop = $user->fresh()->currentShop;

    $this->actingAs($user->fresh())->post(route('onboarding.team'), ['invitations' => []])
        ->assertRedirect(route('onboarding.show'));

    expect(Invitation::where('shop_id', $shop->id)->count())->toBe(0)
        ->and($shop->fresh()->onboarding_step)->toBe('catalog');
});

test('an invalid role in the team step is rejected', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('onboarding.shop'), ['name' => 'X', 'plan' => 'free']);

    $this->actingAs($user->fresh())->post(route('onboarding.team'), [
        'invitations' => [['email' => 'a@example.com', 'role' => 'Owner']],
    ])->assertSessionHasErrors('invitations.0.role');
});

test('the catalog step creates the quick-add rows and finishes onboarding', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('onboarding.shop'), ['name' => 'X', 'plan' => 'free']);
    $this->actingAs($user->fresh())->post(route('onboarding.team'), ['invitations' => []]);

    $this->actingAs($user->fresh())->post(route('onboarding.catalog'), [
        'materials' => [['name' => '1/4in Steel Plate', 'unit' => 'sheet'], ['name' => '']],
        'services' => [['name' => 'Powder Coating']],
        'business_partners' => [['name' => 'Acme Supply']],
    ])->assertRedirect(route('dashboard'));

    $shop = $user->fresh()->currentShop;

    expect($shop->isOnboarded())->toBeTrue()
        ->and($shop->onboarding_step)->toBeNull()
        ->and(Material::where('shop_id', $shop->id)->count())->toBe(1)
        ->and(Material::where('shop_id', $shop->id)->sole()->unit)->toBe('sheet')
        ->and(Service::where('shop_id', $shop->id)->count())->toBe(1)
        ->and(BusinessPartner::where('shop_id', $shop->id)->count())->toBe(1);
});

test('skipping the catalog step still finishes onboarding with an empty catalog', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('onboarding.shop'), ['name' => 'X', 'plan' => 'free']);
    $this->actingAs($user->fresh())->post(route('onboarding.team'), ['invitations' => []]);

    $this->actingAs($user->fresh())->post(route('onboarding.catalog'), [
        'materials' => [], 'services' => [], 'business_partners' => [],
    ])->assertRedirect(route('dashboard'));

    expect($user->fresh()->currentShop->isOnboarded())->toBeTrue();

    // And the now-onboarded shop's dashboard/app routes are reachable.
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
    $this->actingAs($user->fresh())->get(route('customers.index'))->assertOk();
});

test('once onboarded, visiting onboarding again redirects to the dashboard', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('onboarding.show'))->assertRedirect(route('dashboard'));
});

test('the team and catalog steps 404 once the shop is already onboarded', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('onboarding.team'), ['invitations' => []])->assertNotFound();
    $this->actingAs($user)->post(route('onboarding.catalog'), [
        'materials' => [], 'services' => [], 'business_partners' => [],
    ])->assertNotFound();
});
