<?php

use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Shop;
use Database\Seeders\PlanSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('a new shop uses the global defaults until it sets its own', function () {
    config([
        'shop.defaults.timezone' => 'America/Chicago',
        'shop.defaults.stale_quote_days' => 10,
        'shop.defaults.win_rate_window_days' => 60,
    ]);
    $shop = Shop::factory()->create();

    expect($shop->localTimezone())->toBe('America/Chicago')
        ->and($shop->staleQuoteDays())->toBe(10)
        ->and($shop->winRateWindowDays())->toBe(60);

    $shop->update(['stale_quote_days' => 3]);

    expect($shop->fresh()->staleQuoteDays())->toBe(3);
});

test('the owner can update shop settings, and blank fields fall back to the defaults', function () {
    $shop = Shop::factory()->create(['stale_quote_days' => 14]);
    $owner = actingAsShopRole($shop, 'Owner');

    $this->actingAs($owner)->patch(route('shop.settings.update'), [
        'name' => 'Renamed Fab',
        'timezone' => 'America/Denver',
        'stale_quote_days' => '',
        'win_rate_window_days' => 45,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $shop->refresh();
    expect($shop->name)->toBe('Renamed Fab')
        ->and($shop->timezone)->toBe('America/Denver')
        ->and($shop->stale_quote_days)->toBeNull()
        ->and($shop->win_rate_window_days)->toBe(45);
});

test('settings are validated', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopRole($shop, 'Owner');

    $this->actingAs($owner)->patch(route('shop.settings.update'), [
        'name' => '',
        'timezone' => 'Mars/Olympus_Mons',
        'stale_quote_days' => 0,
        'win_rate_window_days' => 5000,
    ])->assertSessionHasErrors(['name', 'timezone', 'stale_quote_days', 'win_rate_window_days']);
});

test('staff cannot see or change shop settings by default', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopRole($shop, 'Staff');

    $this->actingAs($staff)->get(route('shop.settings.edit'))->assertForbidden();
    $this->actingAs($staff)->patch(route('shop.settings.update'), ['name' => 'Hijacked'])->assertForbidden();
});

test('a role the owner granted settings access to can change them', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopRole($shop, 'Admin');
    app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
    Role::findOrCreate('Admin')->givePermissionTo('shop-settings.update');

    $this->actingAs($admin)->get(route('shop.settings.edit'))->assertOk();
    $this->actingAs($admin)->patch(route('shop.settings.update'), ['name' => 'Admin Renamed'])
        ->assertSessionHasNoErrors();

    expect($shop->fresh()->name)->toBe('Admin Renamed');
});

test('settings only ever change the current shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create(['name' => 'Shop B']);
    $owner = actingAsShopRole($shopA, 'Owner');

    $this->actingAs($owner)->patch(route('shop.settings.update'), ['name' => 'Shop A renamed']);

    expect($shopB->fresh()->name)->toBe('Shop B');
});

test('job board drag-and-drop is off by default and only offered to people who can move jobs', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $viewer = actingAsShopMember($shop, 'Staff');
    $shop->members()->updateExistingPivot($viewer->id, ['permission_overrides' => ['jobs.update' => false]]);

    $this->actingAs($owner)->get(route('jobs.index'))
        ->assertInertia(fn ($page) => $page->where('dragEnabled', false));

    $this->actingAs($owner)->patch(route('shop.settings.update'), [
        'name' => $shop->name, 'job_board_drag_enabled' => true,
    ])->assertSessionHasNoErrors();

    expect($shop->fresh()->job_board_drag_enabled)->toBeTrue();

    $this->actingAs($owner)->get(route('jobs.index'))
        ->assertInertia(fn ($page) => $page->where('dragEnabled', true));
    $this->actingAs($viewer->fresh())->get(route('jobs.index'))
        ->assertInertia(fn ($page) => $page->where('dragEnabled', false));
});

test('quote emailing is off by default and can be turned on when the plan includes it', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    expect($shop->fresh()->quote_emailing_enabled)->toBeFalse();

    $this->actingAs($user)->patch(route('shop.settings.update'), ['name' => $shop->name, 'quote_emailing_enabled' => true])
        ->assertSessionHasNoErrors();

    expect($shop->fresh()->quote_emailing_enabled)->toBeTrue()
        ->and($shop->fresh()->quoteEmailingEnabled())->toBeTrue();
});

test('a shop whose plan lacks quote emailing cannot turn it on', function () {
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => null]);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('shop.settings.edit'))
        ->assertInertia(fn ($page) => $page->where('quoteEmailingAvailable', false));

    $this->actingAs($user)->patch(route('shop.settings.update'), ['name' => $shop->name, 'quote_emailing_enabled' => true]);

    expect($shop->fresh()->quote_emailing_enabled)->toBeFalse();
});

test('solo, shop and pro all include quote emailing; free does not', function () {
    $this->seed(PlanSeeder::class);

    expect(Plan::where('key', 'start-up')->first()->hasFeature(Feature::QuoteEmailing))->toBeTrue()
        ->and(Plan::where('key', 'pro')->first()->hasFeature(Feature::QuoteEmailing))->toBeTrue()
        ->and(Plan::where('key', 'solo')->first()->hasFeature(Feature::QuoteEmailing))->toBeTrue()
        ->and(Plan::where('key', 'free')->first()->hasFeature(Feature::QuoteEmailing))->toBeFalse();
});
