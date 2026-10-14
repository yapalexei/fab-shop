<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * What search engines are told about the site, derived from the route table
 * so it can't drift from it:
 *
 *  - the sitemap lists every GET route marked `->sitemap()` (registered as a
 *    Route macro in AppServiceProvider) - a new public page is one call;
 *  - robots.txt hides the first path segment of every route behind `auth` or
 *    `guest` middleware, plus any route marked `->noindex()` (public but
 *    token-bearing, like invitation links).
 *
 * robots.txt is a courtesy to well-behaved crawlers, not access control.
 */
class Seo
{
    /** Paths that aren't app routes with middleware to read this from. */
    private const ALWAYS_HIDDEN = ['horizon', 'up', 'broadcasting'];

    /** @return list<array{loc: string, lastmod: ?string}> */
    public static function sitemapEntries(): array
    {
        $entries = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $meta = $route->getAction('sitemap');

            // A parameterised URI can't be listed without knowing its values.
            if ($meta === null || ! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
                continue;
            }

            $lastmod = ($meta['lastmod'] ?? null) ? config($meta['lastmod']) : null;

            $entries[] = [
                'loc' => url($route->uri()),
                'lastmod' => $lastmod ? Carbon::parse($lastmod)->toDateString() : null,
            ];
        }

        usort($entries, fn ($a, $b) => strcmp($a['loc'], $b['loc']));

        return $entries;
    }

    public static function sitemapXml(): string
    {
        $urls = '';

        foreach (self::sitemapEntries() as $entry) {
            $urls .= '  <url><loc>'.e($entry['loc']).'</loc>'
                .($entry['lastmod'] ? '<lastmod>'.$entry['lastmod'].'</lastmod>' : '')
                ."</url>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls
            .'</urlset>'."\n";
    }

    /** @return list<string> e.g. ['/customers', '/dashboard'] */
    public static function disallowedPaths(): array
    {
        $segments = self::ALWAYS_HIDDEN;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->getAction('sitemap') !== null) {
                continue;
            }

            $private = $route->getAction('noindex') === true
                || collect($route->gatherMiddleware())->contains(
                    fn ($m) => is_string($m) && preg_match('/^(auth|guest)(:|$)/', $m) === 1
                );

            $segment = explode('/', trim($route->uri(), '/'))[0];

            // "/" itself, or a first segment that is a parameter, has no prefix to hide.
            if ($private && $segment !== '' && ! str_starts_with($segment, '{')) {
                $segments[] = $segment;
            }
        }

        $segments = array_unique($segments);
        sort($segments);

        return array_map(fn ($s) => '/'.$s, $segments);
    }

    public static function robotsTxt(): string
    {
        $lines = ['User-agent: *'];

        // Staging, demo and local copies shouldn't end up in a search index,
        // and neither should a shop's private install.
        if (! app()->isProduction() || Edition::selfHosted()) {
            $lines[] = 'Disallow: /';

            return implode("\n", $lines)."\n";
        }

        foreach (self::disallowedPaths() as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('seo.sitemap');

        return implode("\n", $lines)."\n";
    }
}
