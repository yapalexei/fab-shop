<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml, generated from the route table (App\Support\Seo)
 * instead of static files so they follow the routes. There must be no
 * public/robots.txt: the web server would serve it before Laravel is reached.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        return response(Seo::robotsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(): Response
    {
        return response(Seo::sitemapXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
