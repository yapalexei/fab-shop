<?php

use App\Models\Shop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('a shop member can upload photos to a job', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('jobs.photos.store', $job), [
        'photos' => [UploadedFile::fake()->image('progress.jpg')],
    ])->assertRedirect();

    expect($job->fresh()->photos())->toHaveCount(1);
});

test('a non-image upload is rejected', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('jobs.photos.store', $job), [
        'photos' => [UploadedFile::fake()->create('notes.pdf', 100)],
    ])->assertSessionHasErrors('photos.0');

    expect($job->fresh()->photos())->toHaveCount(0);
});

test('a shop member can delete a job photo', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop);
    $user = actingAsShopMember($shop);
    $media = $job->addMedia(UploadedFile::fake()->image('progress.jpg'))->toMediaCollection('photos');

    $this->actingAs($user)->delete(route('jobs.photos.destroy', [$job, $media]))->assertRedirect();

    expect($job->fresh()->photos())->toHaveCount(0);
});

test('a user cannot upload photos to another shop\'s job', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $job = jobForShop($shopB);
    $user = actingAsShopMember($shopA);

    $this->actingAs($user)->post(route('jobs.photos.store', $job), [
        'photos' => [UploadedFile::fake()->image('progress.jpg')],
    ])->assertNotFound();
});

test('a user cannot delete a photo from another shop\'s job', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $job = jobForShop($shopB);
    $media = $job->addMedia(UploadedFile::fake()->image('progress.jpg'))->toMediaCollection('photos');
    $user = actingAsShopMember($shopA);

    $this->actingAs($user)->delete(route('jobs.photos.destroy', [$job, $media]))->assertNotFound();

    expect($job->fresh()->photos())->toHaveCount(1);
});
