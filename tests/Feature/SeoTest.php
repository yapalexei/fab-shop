<?php

use App\Support\Seo;

function asProduction(): void
{
    app()->detectEnvironment(fn () => 'production');
}

test('the sitemap lists the public pages with a lastmod for the legal documents', function () {
    $xml = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect($xml)
        ->toContain('<loc>'.url('/').'</loc>')
        ->toContain('<loc>'.url('/terms').'</loc><lastmod>2026-10-01</lastmod>')
        ->toContain('<loc>'.url('/privacy').'</loc><lastmod>'.config('legal.versions.privacy').'</lastmod>')
        ->toContain('<loc>'.url('/sms-terms').'</loc>')
        // Private and token URLs never appear.
        ->not->toContain('/dashboard')
        ->not->toContain('/invitations')
        ->not->toContain('/login');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

test('a route marked sitemap is picked up without touching the sitemap code', function () {
    Route::get('/pricing-extra', fn () => 'ok')->sitemap();
    Route::get('/members/{id}', fn () => 'ok')->sitemap(); // parameterised: can't be listed

    $locs = array_column(Seo::sitemapEntries(), 'loc');

    expect($locs)->toContain(url('/pricing-extra'))
        ->and(implode(' ', $locs))->not->toContain('{id}')->not->toContain('/members');
});

test('robots.txt hides the signed-in app, sign-in pages and invitation links in production', function () {
    asProduction();

    $robots = $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->getContent();

    expect($robots)
        ->toContain("User-agent: *\n")
        ->toContain('Disallow: /dashboard')
        ->toContain('Disallow: /customers')
        ->toContain('Disallow: /platform-admin')
        ->toContain('Disallow: /login')
        ->toContain('Disallow: /invitations')
        ->toContain('Disallow: /mcp')
        ->toContain('Sitemap: '.route('seo.sitemap'))
        ->not->toContain("Disallow: /\n");
});

test('no page in the sitemap is blocked by robots.txt', function () {
    asProduction();

    $disallowed = Seo::disallowedPaths();

    foreach (Seo::sitemapEntries() as $entry) {
        $path = parse_url($entry['loc'], PHP_URL_PATH) ?: '/';

        foreach ($disallowed as $prefix) {
            expect(str_starts_with($path, $prefix))->toBeFalse("{$path} is blocked by Disallow: {$prefix}");
        }
    }
});

test('anything that is not production asks crawlers to stay out entirely', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false)->assertDontSee('Sitemap:');
});
