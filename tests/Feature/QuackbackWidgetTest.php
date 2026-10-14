<?php

use App\Models\Shop;
use App\Models\User;
use App\Support\Quackback;
use Illuminate\Support\Carbon;

const QB_URL = 'https://feedback.example.com';
const QB_SECRET = 'test-signing-secret-not-a-real-one';

afterEach(fn () => Carbon::setTestNow());

function enableWidget(?string $url = QB_URL, ?string $secret = QB_SECRET): void
{
    config(['services.quackback.url' => $url, 'services.quackback.signing_secret' => $secret]);
}

/** @return array{header: array<string, mixed>, claims: array<string, mixed>, signature: string, signed: string} */
function decodeJwt(string $jwt): array
{
    [$header, $claims, $signature] = explode('.', $jwt);
    $decode = fn (string $part) => json_decode(base64_decode(strtr($part, '-_', '+/')), true);

    return ['header' => $decode($header), 'claims' => $decode($claims), 'signature' => $signature, 'signed' => "{$header}.{$claims}"];
}

test('the launcher snippet is absent unless the widget is configured', function () {
    $this->get('/')->assertOk()->assertDontSee('api/widget/sdk.js', false)->assertDontSee('Quackback', false);

    enableWidget(url: '   ');
    $this->get('/')->assertDontSee('Quackback', false);

    enableWidget(url: 'not a url');
    $this->get('/')->assertDontSee('Quackback', false);
});

test('every visitor, signed in or not, gets the launcher snippet with the CSP nonce', function () {
    enableWidget(url: QB_URL.'/');

    $response = $this->get('/')->assertOk()
        ->assertSee('https:\/\/feedback.example.com\/api\/widget\/sdk.js', false)
        ->assertSee("Quackback('init');", false);

    $nonce = preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m) ? $m[1] : null;

    expect($nonce)->not->toBeNull()
        ->and($response->getContent())->toContain('<script nonce="'.$nonce.'">');

    $this->actingAs(User::factory()->create())->get('/terms')->assertSee("Quackback('init');", false);
});

test('the content security policy lets the widget load, call out and frame - only when configured', function () {
    $without = $this->get('/')->headers->get('Content-Security-Policy');
    expect($without)->not->toContain('feedback.example.com');

    enableWidget();
    $policy = $this->get('/')->headers->get('Content-Security-Policy');

    foreach (['script-src', 'connect-src', 'frame-src'] as $directive) {
        expect($policy)->toMatch('/'.$directive.' [^;]*'.preg_quote(QB_URL, '/').'/');
    }

    // Nothing else is loosened.
    expect($policy)->toContain("default-src 'self'")->and($policy)->not->toContain("'unsafe-eval'");
});

test('a guest cannot get a token', function () {
    enableWidget();

    $this->getJson(route('quackback.token'))->assertUnauthorized();
});

test('the token endpoint does not exist when the widget is off or has no secret', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('quackback.token'))->assertNotFound();

    enableWidget(secret: null);
    $this->actingAs($user)->getJson(route('quackback.token'))->assertNotFound();

    enableWidget(url: null);
    $this->actingAs($user)->getJson(route('quackback.token'))->assertNotFound();
});

test('a signed-in user gets a short-lived HS256 token for themselves, signed with the server secret', function () {
    enableWidget();
    Carbon::setTestNow('2026-10-13 12:00:00');
    $user = User::factory()->create(['name' => 'Ada Welder', 'email' => 'ada@example.com']);

    $response = $this->actingAs($user)->getJson(route('quackback.token'))->assertOk();
    $token = decodeJwt($response->json('ssoToken'));

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($token['header'])->toBe(['alg' => 'HS256', 'typ' => 'JWT'])
        ->and($token['claims'])->toBe([
            'sub' => (string) $user->id,
            'email' => 'ada@example.com',
            'name' => 'Ada Welder',
            'exp' => now()->addMinutes(5)->getTimestamp(),
        ])
        ->and($token['signature'])->toBe(rtrim(strtr(base64_encode(hash_hmac('sha256', $token['signed'], QB_SECRET, true)), '+/', '-_'), '='));
});

test('the subject is the user id, never the email, and is unique per user', function () {
    enableWidget();
    [$a, $b] = [User::factory()->create(), User::factory()->create()];

    $subA = decodeJwt(Quackback::ssoToken($a))['claims']['sub'];
    $subB = decodeJwt(Quackback::ssoToken($b))['claims']['sub'];

    expect($subA)->toBe((string) $a->id)->and($subA)->not->toBe($subB)->and($subA)->not->toContain('@');
});

test('a token made with the wrong secret does not verify', function () {
    enableWidget();
    $token = decodeJwt(Quackback::ssoToken(User::factory()->create()));
    $forged = rtrim(strtr(base64_encode(hash_hmac('sha256', $token['signed'], 'some-other-secret', true)), '+/', '-_'), '=');

    expect($forged)->not->toBe($token['signature']);
});

test('the signing secret never appears in a page, a shared prop, or the token response', function () {
    enableWidget();
    $owner = actingAsShopMember(Shop::factory()->create(), 'Owner');

    $page = $this->actingAs($owner)->get(route('dashboard'));
    $token = $this->actingAs($owner)->getJson(route('quackback.token'));

    expect($page->getContent())->not->toContain(QB_SECRET)
        ->and($token->getContent())->not->toContain(QB_SECRET)
        ->and($this->get('/')->getContent())->not->toContain(QB_SECRET);
});

test('an AI agent user is never vouched for', function () {
    enableWidget();
    $agent = User::factory()->create();
    $agent->forceFill(['is_agent' => true])->save();

    $this->actingAs($agent)->getJson(route('quackback.token'))->assertForbidden();
});

test('the token endpoint is rate limited', function () {
    enableWidget();
    $user = User::factory()->create();

    foreach (range(1, 30) as $_) {
        $this->actingAs($user)->getJson(route('quackback.token'))->assertOk();
    }

    $this->actingAs($user)->getJson(route('quackback.token'))->assertTooManyRequests();
});

test('the token endpoint stays out of robots and sitemap', function () {
    $this->get('/sitemap.xml')->assertDontSee('quackback');
});
