<?php

use App\Enums\ConsentType;
use App\Enums\Feature;
use App\Models\ConsentRecord;
use App\Models\Plan;
use App\Models\Shop;
use App\Support\Consent;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Collection;

beforeEach(fn () => $this->seed(PlanSeeder::class));

/** An owner of a shop on Solo with a paid period running, who has NOT agreed to the current Terms. */
function unagreedOwner(string $plan = 'solo'): array
{
    $shop = Shop::factory()->create([
        'plan_id' => Plan::offeredByKey($plan)->id,
        'trial_ends_at' => now()->subMonth(),
        'subscription_renews_at' => now()->addDays(10),
    ]);

    return [$shop, actingAsShopMember($shop, 'Owner', agreedToTerms: false)];
}

function termsRecords(): Collection
{
    return ConsentRecord::query()->where('source', 'subscription')->get();
}

test('the Terms page states how many days of notice a price increase gets, from the same setting as the admin form', function () {
    config(['shop.price_increase_notice_days' => 21]);

    $this->get(route('legal.terms'))->assertInertia(fn ($page) => $page
        ->component('Legal/Terms')
        ->where('priceNoticeDays', 21)
        ->where('effectiveDate', config('legal.versions.terms')));

    $this->actingAs(actingAsPlatformAdmin())->get(route('platform-admin.plans.index'))
        ->assertInertia(fn ($page) => $page->where('priceNoticeDays', 21));
});

test('the Terms version was bumped for the price-change wording', function () {
    expect(config('legal.versions.terms'))->toBeGreaterThanOrEqual('2026-10-13');
});

test('changing plan needs the owner to agree to the current Terms first, and records it', function () {
    [$shop, $owner] = unagreedOwner();

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up'])->assertSessionHasErrors('accepted_terms');
    expect($shop->fresh()->plan->key)->toBe('solo')->and(termsRecords())->toHaveCount(0);

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up', 'accepted_terms' => true])->assertSessionHasNoErrors();

    $records = termsRecords();
    expect($shop->fresh()->plan->key)->toBe('start-up')
        ->and($records)->toHaveCount(2)
        ->and($records->pluck('type')->all())->toContain(ConsentType::TermsOfService, ConsentType::PrivacyPolicy)
        ->and($records->every(fn ($r) => $r->granted && $r->user_id === $owner->id && $r->shop_id === $shop->id && $r->consent_text === Consent::AGREEMENT))->toBeTrue()
        ->and($records->firstWhere('type', ConsentType::TermsOfService)->document_version)->toBe(config('legal.versions.terms'));
});

test('once they have agreed they are not asked again', function () {
    [$shop, $owner] = unagreedOwner();
    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up', 'accepted_terms' => true]);

    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'pro'])->assertSessionHasNoErrors();

    expect($shop->fresh()->plan->key)->toBe('pro')->and(termsRecords())->toHaveCount(2);
});

test('subscribing to an add-on needs the agreement too', function () {
    [$shop, $owner] = unagreedOwner();

    $this->actingAs($owner)->post(route('shop.addons.store', Feature::MaterialTracking->value), ['confirm_separate' => true])
        ->assertSessionHasErrors('accepted_terms');
    expect($shop->featureSubscriptions()->withoutGlobalScopes()->count())->toBe(0);

    $this->actingAs($owner)->post(route('shop.addons.store', Feature::MaterialTracking->value), ['confirm_separate' => true, 'accepted_terms' => true])
        ->assertSessionHasNoErrors();
    expect($shop->featureSubscriptions()->withoutGlobalScopes()->count())->toBe(1)->and(termsRecords())->toHaveCount(2);
});

test('buying seats needs it, but giving seats back does not', function () {
    [$shop, $owner] = unagreedOwner('start-up');

    $this->actingAs($owner)->put(route('shop.seats.update'), ['extra_seats' => 2])->assertSessionHasErrors('accepted_terms');
    expect($shop->fresh()->extra_seats)->toBe(0);

    $this->actingAs($owner)->put(route('shop.seats.update'), ['extra_seats' => 2, 'accepted_terms' => true])->assertSessionHasNoErrors();
    expect($shop->fresh()->extra_seats)->toBe(2);

    $other = unagreedOwner('start-up');
    $other[0]->update(['extra_seats' => 2]);
    $this->actingAs($other[1])->put(route('shop.seats.update'), ['extra_seats' => 1])->assertSessionHasNoErrors();
    expect($other[0]->fresh()->extra_seats)->toBe(1);
});

test('agreeing to an older version of the Terms is not enough', function () {
    [$shop, $owner] = unagreedOwner();
    ConsentRecord::query()->create([
        'user_id' => $owner->id, 'shop_id' => $shop->id, 'subject_email' => $owner->email, 'type' => ConsentType::TermsOfService,
        'granted' => true, 'document_version' => '2026-10-01', 'consent_text' => Consent::AGREEMENT, 'source' => 'registration',
    ]);

    expect(Consent::hasAcceptedCurrentTerms($owner))->toBeFalse();
    $this->actingAs($owner)->post(route('shop.plan.update'), ['plan' => 'start-up'])->assertSessionHasErrors('accepted_terms');
});

test('a withdrawn agreement is not an agreement', function () {
    [, $owner] = unagreedOwner();
    agreeToCurrentTerms($owner);
    expect(Consent::hasAcceptedCurrentTerms($owner))->toBeTrue();

    Consent::record(ConsentType::TermsOfService, false, 'withdrawn', 'test', request(), user: $owner);

    expect(Consent::hasAcceptedCurrentTerms($owner))->toBeFalse();
});

test('the confirmation dialogs are told whether to ask, with the exact wording', function () {
    [, $owner] = unagreedOwner();

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page
        ->where('terms.accepted', false)->where('terms.text', Consent::AGREEMENT));
    $this->actingAs($owner)->get(route('shop.addons.index'))->assertInertia(fn ($page) => $page->where('terms.accepted', false));

    agreeToCurrentTerms($owner);

    $this->actingAs($owner)->get(route('shop.plan.index'))->assertInertia(fn ($page) => $page->where('terms.accepted', true));
});

test('one person agreeing does not cover another', function () {
    [$shop, $owner] = unagreedOwner();
    $admin = actingAsShopMember($shop, 'Owner', agreedToTerms: true);

    expect(Consent::hasAcceptedCurrentTerms($admin))->toBeTrue()->and(Consent::hasAcceptedCurrentTerms($owner))->toBeFalse();
});

test('signing up still requires the box', function () {
    $this->post(route('register'), ['name' => 'A', 'email' => 'a@example.com', 'password' => 'password-123', 'password_confirmation' => 'password-123'])
        ->assertSessionHasErrors('accepted_terms');
});
