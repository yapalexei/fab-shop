<?php

namespace App\Support;

use Illuminate\Http\Request;
use Tighten\Ziggy\Ziggy;

/**
 * Which requests are server-rendered by the Inertia SSR service: the public
 * pages search engines index - exactly the routes marked `->sitemap()` (see
 * Seo). Everything behind a login stays client-rendered, so those pages never
 * depend on the Node service, and a new public page is rendered for search by
 * the same call that lists it in sitemap.xml.
 *
 * Only the first, full-page load is rendered; Inertia's own XHR navigations
 * don't need HTML.
 */
class PublicSsr
{
    public static function appliesTo(?Request $request = null): bool
    {
        $request ??= request();

        return config('inertia.ssr.enabled', true)
            && $request->route()?->getAction('sitemap') !== null
            && ! $request->inertia();
    }

    /**
     * Route table for the SSR process, which has no window.Ziggy.
     *
     * @return array<string, mixed>
     */
    public static function ziggy(): array
    {
        return (new Ziggy('public'))->toArray();
    }
}
