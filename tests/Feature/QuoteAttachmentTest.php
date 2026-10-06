<?php

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Quote;
use App\Models\Shop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function quoteForShop(Shop $shop): Quote
{
    return Quote::factory()->for($shop)->create([
        'customer_id' => Customer::factory()->for($shop)->create()->id,
    ]);
}

test('files can be attached when creating a quote', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'line_items' => [
            ['type' => 'material', 'description' => 'Plate', 'quantity' => 1, 'unit_cost' => 10, 'markup_percent' => 0, 'override_total' => null],
        ],
        'attachments' => [UploadedFile::fake()->create('blueprint.pdf', 500, 'application/pdf')],
    ])->assertRedirect();

    expect(Quote::sole()->attachments())->toHaveCount(1);
});

test('a member can upload to and delete from a saved quote', function () {
    $shop = Shop::factory()->create();
    $quote = quoteForShop($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.attachments.store', $quote), [
        'attachments' => [UploadedFile::fake()->create('drawing.dxf', 100)],
    ])->assertRedirect();

    $media = $quote->fresh()->attachments()->sole();

    $this->actingAs($user)->get(route('quotes.attachments.show', [$quote, $media->id]))->assertOk();

    $this->actingAs($user)->delete(route('quotes.attachments.destroy', [$quote, $media->id]))->assertRedirect();

    expect($quote->fresh()->attachments())->toHaveCount(0);
});

test('files over 20 MB are rejected', function () {
    $shop = Shop::factory()->create();
    $quote = quoteForShop($shop);
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.attachments.store', $quote), [
        'attachments' => [UploadedFile::fake()->create('huge.pdf', 20481)],
    ])->assertSessionHasErrors('attachments.0');

    expect($quote->fresh()->attachments())->toHaveCount(0);
});

test('attachments carry over to the job when the quote is converted', function () {
    $shop = Shop::factory()->create();
    $quote = quoteForShop($shop);
    $quote->lineItems()->create(['type' => 'material', 'description' => 'Item', 'quantity' => 1, 'unit_cost' => 10, 'markup_percent' => 0]);
    $quote->update(['status' => QuoteStatus::Won]);
    $quote->addMedia(UploadedFile::fake()->create('blueprint.pdf', 100, 'application/pdf'))->toMediaCollection('attachments');
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('quotes.convert', $quote))->assertRedirect();

    $job = Job::sole();
    $copy = $job->attachments()->sole();

    expect($copy->file_name)->toBe('blueprint.pdf')
        ->and($quote->fresh()->attachments())->toHaveCount(1);

    $this->actingAs($user)->get(route('jobs.attachments.show', [$job, $copy->id]))->assertOk();
});

test('a user cannot touch another shop\'s quote attachments', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    $quote = quoteForShop($shopB);
    $media = $quote->addMedia(UploadedFile::fake()->create('secret.pdf', 10))->toMediaCollection('attachments');
    $user = actingAsShopMember($shopA);

    $this->actingAs($user)->post(route('quotes.attachments.store', $quote), [
        'attachments' => [UploadedFile::fake()->create('x.pdf', 10)],
    ])->assertNotFound();
    $this->actingAs($user)->get(route('quotes.attachments.show', [$quote, $media->id]))->assertNotFound();
    $this->actingAs($user)->delete(route('quotes.attachments.destroy', [$quote, $media->id]))->assertNotFound();

    expect($quote->fresh()->attachments())->toHaveCount(1);
});

test('a member of the same shop cannot reach another quote\'s file through their own quote', function () {
    $shop = Shop::factory()->create();
    $mine = quoteForShop($shop);
    $other = quoteForShop($shop);
    $media = $other->addMedia(UploadedFile::fake()->create('other.pdf', 10))->toMediaCollection('attachments');
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('quotes.attachments.show', [$mine, $media->id]))->assertNotFound();
});

test('attachment props describe each file with a kind, thumbnail and file path', function () {
    $shop = Shop::factory()->create();
    $quote = quoteForShop($shop);
    $quote->addMedia(UploadedFile::fake()->image('photo.jpg', 800, 600))->toMediaCollection('attachments');
    $quote->addMedia(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'))->toMediaCollection('attachments');
    $quote->addMedia(UploadedFile::fake()->createWithContent('part.step', 'ISO-10303-21;'))->toMediaCollection('attachments');
    $quote->addMedia(UploadedFile::fake()->createWithContent('part.stl', 'solid x'))->toMediaCollection('attachments');
    $user = actingAsShopMember($shop);

    $files = $this->actingAs($user)->get(route('quotes.edit', $quote))
        ->assertOk()->viewData('page')['props']['quote']['attachments'];
    $byName = collect($files)->keyBy('name');

    expect($byName['photo.jpg']['kind'])->toBe('image')
        ->and($byName['photo.jpg']['thumbnail'])->toContain('thumbnail=1')
        ->and($byName['logo.svg']['kind'])->toBe('image')
        ->and($byName['logo.svg']['thumbnail'])->not->toBeNull()
        ->and($byName['part.step']['kind'])->toBe('step')
        ->and($byName['part.step']['thumbnail'])->toBeNull()
        ->and($byName['part.stl']['kind'])->toBe('stl')
        ->and($byName['part.stl']['thumbnail'])->toBeNull()
        ->and($byName['part.stl']['file_path'])->toContain("attachments/{$byName['part.stl']['id']}");

    $this->actingAs($user)->get($byName['photo.jpg']['thumbnail'])->assertOk()->assertHeader('content-type', 'image/jpeg');

    $svg = $this->actingAs($user)->get($byName['logo.svg']['file_path'])->assertOk();
    expect($svg->headers->get('Content-Security-Policy'))->toContain('sandbox');
});
