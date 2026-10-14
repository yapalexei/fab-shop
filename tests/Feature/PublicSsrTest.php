<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Shop;
use App\Support\PublicSsr;
use App\Support\Ssr\PublicPagesGateway;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\Gateway;
use Tighten\Ziggy\Ziggy;

beforeEach(function () {
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.ensure_bundle_exists' => false,
        'inertia.ssr.url' => 'http://ssr.test:13714',
    ]);
});

function fakeRenderer(): void
{
    Http::fake(['ssr.test:13714/render' => Http::response([
        'head' => ['<title inertia>Rendered title - Fab Shop</title>'],
        'body' => '<div id="app" data-page="{}"><h1>Rendered on the server</h1></div>',
    ])]);
}

test('the marketing page is server-rendered and carries the renderer\'s head and body', function () {
    $this->seed(PlanSeeder::class);
    fakeRenderer();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<h1>Rendered on the server</h1>')
        ->toContain('<title inertia>Rendered title - Fab Shop</title>')
        // The shell's own fallback title must not sit next to the rendered one.
        ->and(substr_count($html, '<title'))->toBe(1);
});

test('the legal pages are server-rendered too', function (string $path) {
    fakeRenderer();

    $this->get($path)->assertOk()->assertSee('Rendered on the server', false);
})->with(['/terms', '/privacy', '/sms-terms']);

test('pages behind a login are never sent to the renderer', function () {
    fakeRenderer();
    $user = actingAsShopMember(Shop::factory()->create());

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('Rendered on the server', false);

    Http::assertNothingSent();
});

test('Inertia\'s own navigations get JSON, not a render', function () {
    $this->seed(PlanSeeder::class);
    fakeRenderer();

    $this->get('/', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ])->assertOk();

    Http::assertNothingSent();
});

test('a renderer that is down leaves the page client-rendered, with its title and meta intact', function () {
    $this->seed(PlanSeeder::class);
    Http::fake(['ssr.test:13714/*' => Http::response('boom', 500)]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<div id="app" data-page=')
        ->toContain('<title inertia>Quoting, jobs &amp; invoicing for metal fab shops - '.config('app.name').'</title>')
        ->toContain('<meta name="description"')
        ->and(substr_count($html, '<title'))->toBe(1);
});

test('the page is rendered by posting it to the \'/render\' endpoint', function () {
    $this->seed(PlanSeeder::class);
    fakeRenderer();

    $this->get('/')->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'http://ssr.test:13714/render');
});

test('nothing is sent when SSR is switched off', function () {
    config(['inertia.ssr.enabled' => false]);
    $this->seed(PlanSeeder::class);
    Http::fake();

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('ziggy', null)->etc());

    Http::assertNothingSent();
});

test('the gateway in use is the public-pages one', function () {
    expect(app(Gateway::class))->toBeInstanceOf(PublicPagesGateway::class);
});

test('a server-rendered page is handed the route table the renderer needs', function () {
    $this->seed(PlanSeeder::class);
    fakeRenderer();

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('ziggy.routes', fn ($routes) => array_key_exists('login', $routes->toArray())
            && array_key_exists('legal.terms', $routes->toArray())
            && ! array_key_exists('customers.index', $routes->toArray())));
});

test('every route a server-rendered page calls is in the public ziggy group', function () {
    $files = array_merge(
        [resource_path('js/Pages/Welcome.tsx'), resource_path('js/Components/LegalLinks.tsx'), resource_path('js/Components/PlanComparisonTable.tsx'), resource_path('js/Layouts/LegalLayout.tsx')],
        glob(resource_path('js/Pages/Legal/*.tsx')),
        glob(resource_path('js/Components/marketing/*.tsx')),
    );
    $available = array_keys((new Ziggy('public'))->toArray()['routes']);
    $used = [];

    foreach ($files as $file) {
        preg_match_all("/\broute\(\s*'([^']+)'/", file_get_contents($file), $matches);
        $used = [...$used, ...$matches[1]];
    }

    expect($used)->not->toBeEmpty()
        ->and(array_diff(array_unique($used), $available))->toBe([], 'Add these route names to ziggy.groups.public in config/ziggy.php');
});

test('only sitemap routes qualify for server rendering', function () {
    $request = Request::create('/customers');

    expect(PublicSsr::appliesTo($request))->toBeFalse();
});
