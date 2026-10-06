<?php

use App\Enums\ShopResource;
use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Shop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

test('a customer logo can be uploaded on create, replaced and removed', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($user)->post(route('customers.store'), [
        'name' => 'Acme Fab',
        'logo' => UploadedFile::fake()->image('acme.png', 300, 200),
    ])->assertSessionHasNoErrors();

    $customer = Customer::sole();
    expect($customer->logoUrl())->not->toBeNull();

    $this->actingAs($user)->get($customer->logoUrl())->assertOk();

    // Updates are a POST spoofing PUT, so the multipart body is parsed.
    $this->actingAs($user)->post(route('customers.update', $customer), [
        '_method' => 'put',
        'name' => 'Acme Fab',
        'logo' => UploadedFile::fake()->image('new.jpg'),
    ])->assertSessionHasNoErrors();

    expect(Customer::sole()->getMedia('logo'))->toHaveCount(1)
        ->and(Customer::sole()->getFirstMedia('logo')->file_name)->toBe('new.jpg');

    // Saving without touching the logo keeps it.
    $this->actingAs($user)->put(route('customers.update', $customer), ['name' => 'Acme Fabrication']);
    expect(Customer::sole()->logoUrl())->not->toBeNull();

    $this->actingAs($user)->put(route('customers.update', $customer), ['name' => 'Acme Fabrication', 'remove_logo' => true]);
    expect(Customer::sole()->logoUrl())->toBeNull();
});

test('logos must be images no bigger than 5 MB', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($user)->post(route('customers.store'), [
        'name' => 'A',
        'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('logo');

    $this->actingAs($user)->post(route('customers.store'), [
        'name' => 'B',
        'logo' => UploadedFile::fake()->image('big.png')->size(5121),
    ])->assertSessionHasErrors('logo');

    expect(Customer::count())->toBe(0);
});

test('a business partner logo can be uploaded and removed', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($user)->post(route('business-partners.store'), [
        'name' => 'Metals R Us',
        'logo' => UploadedFile::fake()->image('m.png'),
    ])->assertSessionHasNoErrors();

    $partner = BusinessPartner::sole();
    expect($partner->logoUrl())->not->toBeNull();
    $this->actingAs($user)->get($partner->logoUrl())->assertOk();

    $this->actingAs($user)->post(route('business-partners.update', $partner), [
        '_method' => 'put',
        'name' => 'Metals R Us',
        'remove_logo' => true,
    ])->assertSessionHasNoErrors();

    expect(BusinessPartner::sole()->logoUrl())->toBeNull();
});

test('another shop\'s logos are not reachable', function () {
    $mine = Shop::factory()->create();
    $theirs = Shop::factory()->create();
    $user = actingAsShopMember($mine);

    $customer = Customer::factory()->for($theirs)->create();
    $customer->addMedia(UploadedFile::fake()->image('x.png'))->toMediaCollection('logo');
    $partner = BusinessPartner::factory()->for($theirs)->create();
    $partner->addMedia(UploadedFile::fake()->image('x.png'))->toMediaCollection('logo');

    $this->actingAs($user)->get(route('customers.logo', $customer))->assertNotFound();
    $this->actingAs($user)->get(route('business-partners.logo', $partner))->assertNotFound();
});

test('the logo route needs permission to see customers, quotes or jobs', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $customer->addMedia(UploadedFile::fake()->image('x.png'))->toMediaCollection('logo');
    $staff = actingAsShopMember($shop, 'Staff');
    // Any action on a resource implies seeing it, so deny them all.
    $denied = collect(['customers', 'quotes', 'jobs'])
        ->flatMap(fn ($resource) => collect(ShopResource::from($resource)->actions())->keys()->map(fn ($action) => "{$resource}.{$action}"))
        ->mapWithKeys(fn ($permission) => [$permission => false])
        ->all();
    $shop->members()->updateExistingPivot($staff->id, ['permission_overrides' => $denied]);

    $this->actingAs($staff->fresh())->get(route('customers.logo', $customer))->assertForbidden();
});

test('the quotes list carries each customer\'s logo, null when there is none', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $withLogo = Customer::factory()->for($shop)->create();
    $withLogo->addMedia(UploadedFile::fake()->image('x.png'))->toMediaCollection('logo');
    $plain = Customer::factory()->for($shop)->create();

    Quote::factory()->for($shop)->for($withLogo)->create();
    Quote::factory()->for($shop)->for($plain)->create();

    $this->actingAs($user)->get(route('quotes.index'))->assertInertia(fn ($page) => $page
        ->has('quotes', 2)
        ->where('quotes', fn ($quotes) => $quotes->pluck('customer_logo_url')->filter()->count() === 1));
});
