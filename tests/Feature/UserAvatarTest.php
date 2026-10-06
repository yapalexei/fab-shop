<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

test('a user can set and remove their profile picture', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($user)
        ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 600, 400)])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->avatarUrl())->not->toBeNull();

    $this->actingAs($user)->get($user->fresh()->avatarUrl())->assertOk();

    $this->actingAs($user->fresh())->delete('/profile/avatar');

    expect($user->fresh()->avatarUrl())->toBeNull();
});

test('a new picture replaces the old one', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    foreach (['a.png', 'b.png'] as $name) {
        $this->actingAs($user->fresh())->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image($name)]);
    }

    expect($user->fresh()->getMedia('avatar'))->toHaveCount(1);
});

test('only images up to 5 MB are accepted', function () {
    $user = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($user)
        ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('avatar');

    $this->actingAs($user)
        ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('huge.png')->size(5121)])
        ->assertSessionHasErrors('avatar');

    expect($user->fresh()->avatarUrl())->toBeNull();
});

test('a teammate can see the picture but a user in another shop cannot', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);
    $mate = actingAsShopMember($shop);
    $stranger = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($owner)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('o.png')]);
    $url = $owner->fresh()->avatarUrl();

    $this->actingAs($mate)->get($url)->assertOk();
    $this->actingAs($stranger)->get($url)->assertNotFound();
});

test('the shared auth user carries the picture url', function () {
    $user = actingAsShopMember(Shop::factory()->create());
    $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('o.png')]);

    $this->actingAs($user->fresh())->get('/profile')
        ->assertInertia(fn ($page) => $page->where('auth.user.avatar_url', $user->fresh()->avatarUrl()));
});
