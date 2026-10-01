<?php

use App\Enums\ConsentType;
use App\Models\ConsentRecord;
use App\Models\Invitation;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\ShopInvitationSent;
use App\Support\Consent;
use Illuminate\Notifications\AnonymousNotifiable;

function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'accepted_terms' => true,
        ...$overrides,
    ];
}

test('the legal pages are public', function () {
    // Page bundles are built separately; only the server side is under test.
    $this->withoutVite();

    foreach (['legal.terms', 'legal.privacy', 'legal.sms'] as $page) {
        $this->get(route($page))->assertOk();
    }

    $this->get(route('legal.terms'))->assertInertia(fn ($p) => $p
        ->component('Legal/Terms')
        ->where('effectiveDate', config('legal.versions.terms'))
        ->has('company.name'));
});

test('registering requires agreeing to the terms and privacy policy', function () {
    $this->post('/register', registrationPayload(['accepted_terms' => false]))->assertSessionHasErrors('accepted_terms');
    $this->post('/register', registrationPayload(['accepted_terms' => null]))->assertSessionHasErrors('accepted_terms');

    expect(User::count())->toBe(0)->and(ConsentRecord::count())->toBe(0);
});

test('registering records what was agreed to, its version, wording, ip and browser', function () {
    $this->withHeader('User-Agent', 'TestBrowser/1.0')
        ->post('/register', registrationPayload())
        ->assertSessionHasNoErrors();

    $user = User::sole();
    $records = ConsentRecord::query()->where('user_id', $user->id)->get()->keyBy(fn ($r) => $r->type->value);

    expect($records)->toHaveCount(2)
        ->and($records['terms']->granted)->toBeTrue()
        ->and($records['terms']->document_version)->toBe(config('legal.versions.terms'))
        ->and($records['privacy']->document_version)->toBe(config('legal.versions.privacy'))
        ->and($records['terms']->consent_text)->toBe(Consent::AGREEMENT)
        ->and($records['terms']->source)->toBe('registration')
        ->and($records['terms']->subject_email)->toBe('test@example.com')
        ->and($records['terms']->ip_address)->not->toBeNull()
        ->and($records['terms']->user_agent)->toBe('TestBrowser/1.0');
});

test('accepting an invitation as a new person needs and records the agreement', function () {
    $shop = Shop::factory()->create();
    $invitation = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'new@example.com', 'role' => 'Staff',
        'token' => 'tok-consent', 'expires_at' => now()->addDays(7),
    ]);

    $this->post(route('invitations.accept', $invitation->token), [
        'name' => 'New Person', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasErrors('accepted_terms');

    $this->post(route('invitations.accept', $invitation->token), [
        'name' => 'New Person', 'password' => 'password', 'password_confirmation' => 'password', 'accepted_terms' => true,
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'new@example.com')->sole();

    expect(ConsentRecord::query()->where('user_id', $user->id)->pluck('source')->unique()->all())->toBe(['invitation'])
        ->and(ConsentRecord::query()->where('user_id', $user->id)->first()->shop_id)->toBe($shop->id);
});

test('consent records are append-only', function () {
    $record = Consent::record(ConsentType::TermsOfService, true, 'text', 'test', request());

    expect(fn () => $record->update(['granted' => false]))->toThrow(LogicException::class)
        ->and(fn () => $record->delete())->toThrow(LogicException::class);
});

test('sms consent is recorded per phone number with the exact opt-in wording, and the latest record wins', function () {
    $user = User::factory()->create();

    expect(Consent::hasSmsConsent('+15555550100'))->toBeFalse();

    $in = Consent::recordSms('+15555550100', true, 'profile', request(), $user);

    expect($in->consent_text)->toBe(Consent::smsOptInText())
        ->and($in->document_version)->toBe(config('legal.versions.sms'))
        ->and(Consent::hasSmsConsent('+15555550100'))->toBeTrue();

    Consent::recordSms('+15555550100', false, 'reply-stop', request(), $user, text: 'STOP');

    expect(Consent::hasSmsConsent('+15555550100'))->toBeFalse()
        ->and(ConsentRecord::query()->where('subject_phone', '+15555550100')->count())->toBe(2);
});

test('the sms opt-in wording carries the required disclosures', function () {
    $text = Consent::smsOptInText();

    expect($text)->toContain('Message frequency varies')
        ->and($text)->toContain('Message and data rates may apply')
        ->and($text)->toContain('STOP')
        ->and($text)->toContain('HELP')
        ->and($text)->toContain('not a condition of purchase');
});

test('deleting a user keeps their consent records', function () {
    $user = User::factory()->create(['email' => 'gone@example.com']);
    Consent::recordAgreement($user, 'registration', request());

    $user->delete();

    $record = ConsentRecord::query()->first();
    expect($record->user_id)->toBeNull()->and($record->subject_email)->toBe('gone@example.com');
});

test('emails carry links to the terms and privacy policy', function () {
    $shop = Shop::factory()->create();
    $invitation = Invitation::create([
        'shop_id' => $shop->id, 'email' => 'x@example.com', 'role' => 'Staff',
        'token' => 'tok-mail', 'expires_at' => now()->addDays(7),
    ]);

    $html = (string) (new ShopInvitationSent($invitation))->toMail(new AnonymousNotifiable)->render();

    expect($html)->toContain(route('legal.terms'))->and($html)->toContain(route('legal.privacy'));
});
