<?php

use App\Models\Certification;
use App\Models\Shop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

function certificationFor(Shop $shop, $user): Certification
{
    return Certification::factory()->for($shop)->create(['user_id' => $user->id]);
}

test('documents can be attached when a certification is created', function () {
    $user = actingAsShopMember($shop = Shop::factory()->create());

    $this->actingAs($user)->post(route('certifications.store'), [
        'user_id' => $user->id,
        'documents' => [
            UploadedFile::fake()->create('cert.pdf', 200, 'application/pdf'),
            UploadedFile::fake()->image('card.jpg'),
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('certifications.index'));

    expect(Certification::sole()->documents())->toHaveCount(2);
});

test('a certification takes pdfs and images up to 30 MB, at most 3 files', function () {
    $user = actingAsShopMember($shop = Shop::factory()->create());

    $post = fn (array $documents) => $this->actingAs($user)->post(route('certifications.store'), [
        'user_id' => $user->id,
        'documents' => $documents,
    ]);

    // Right at the limit is fine.
    $post([UploadedFile::fake()->create('big.pdf', 30 * 1024, 'application/pdf')])->assertSessionHasNoErrors();

    $post([UploadedFile::fake()->create('big.pdf', 30 * 1024 + 1, 'application/pdf')])->assertSessionHasErrors('documents.0');
    $post([UploadedFile::fake()->create('notes.docx', 10)])->assertSessionHasErrors('documents.0');
    $post([UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('documents.0');
    $post(array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.png"), [1, 2, 3, 4]))->assertSessionHasErrors('documents');

    expect(Certification::count())->toBe(1);
});

test('documents can be added to and removed from a saved certification', function () {
    $user = actingAsShopMember($shop = Shop::factory()->create());
    $certification = certificationFor($shop, $user);

    $this->actingAs($user)->post(route('certifications.documents.store', $certification), [
        'documents' => [UploadedFile::fake()->create('cert.pdf', 50, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $document = $certification->fresh()->documents()->first();

    $this->actingAs($user)->get(route('certifications.documents.show', [$certification, $document->id]))->assertOk();

    $this->actingAs($user)->delete(route('certifications.documents.destroy', [$certification, $document->id]))->assertRedirect();

    expect($certification->fresh()->documents())->toHaveCount(0);
});

test('a saved certification cannot go past 3 documents in total', function () {
    $user = actingAsShopMember($shop = Shop::factory()->create());
    $certification = certificationFor($shop, $user);

    foreach (range(1, 2) as $i) {
        $certification->fresh()->addMedia(UploadedFile::fake()->image("a{$i}.png"))->toMediaCollection('documents');
    }

    $this->actingAs($user)->post(route('certifications.documents.store', $certification), [
        'documents' => [UploadedFile::fake()->image('b1.png'), UploadedFile::fake()->image('b2.png')],
    ])->assertSessionHasErrors('documents');

    $this->actingAs($user)->post(route('certifications.documents.store', $certification), [
        'documents' => [UploadedFile::fake()->image('b1.png')],
    ])->assertSessionHasNoErrors();

    expect($certification->fresh()->documents())->toHaveCount(3);
});

test('the list says which certifications have documents', function () {
    $user = actingAsShopMember($shop = Shop::factory()->create());
    $with = certificationFor($shop, $user);
    $with->addMedia(UploadedFile::fake()->image('a.png'))->toMediaCollection('documents');
    certificationFor($shop, $user);

    $this->actingAs($user)->get(route('certifications.index'))->assertInertia(fn ($page) => $page
        ->where('certifications', fn ($rows) => $rows->pluck('documents_count', 'id')->all() === [
            $with->id => 1,
            $with->id + 1 => 0,
        ]));
});

test('another shop\'s certification documents are unreachable', function () {
    $mine = Shop::factory()->create();
    $theirs = Shop::factory()->create();
    $user = actingAsShopMember($mine);
    $owner = actingAsShopMember($theirs);

    $certification = certificationFor($theirs, $owner);
    $certification->addMedia(UploadedFile::fake()->image('a.png'))->toMediaCollection('documents');
    $document = $certification->documents()->first();

    $this->actingAs($user)->get(route('certifications.documents.show', [$certification, $document->id]))->assertNotFound();
    $this->actingAs($user)->post(route('certifications.documents.store', $certification), [
        'documents' => [UploadedFile::fake()->image('x.png')],
    ])->assertNotFound();
    $this->actingAs($user)->delete(route('certifications.documents.destroy', [$certification, $document->id]))->assertNotFound();
});

test('view-only members can open documents but not change them', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);
    $staff = actingAsShopMember($shop, 'Staff');
    $certification = certificationFor($shop, $owner);
    $certification->addMedia(UploadedFile::fake()->image('a.png'))->toMediaCollection('documents');
    $document = $certification->documents()->first();

    $this->actingAs($staff)->get(route('certifications.documents.show', [$certification, $document->id]))->assertOk();
    $this->actingAs($staff)->post(route('certifications.documents.store', $certification), [
        'documents' => [UploadedFile::fake()->image('x.png')],
    ])->assertForbidden();
    $this->actingAs($staff)->delete(route('certifications.documents.destroy', [$certification, $document->id]))->assertForbidden();
});
