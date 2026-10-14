<?php

namespace App\Support;

/**
 * What a public page tells search engines and link-preview scrapers about
 * itself: description, canonical URL, Open Graph / Twitter card and JSON-LD.
 *
 * Rendered by resources/views/partials/seo.blade.php straight into the HTML
 * shell, deliberately not by the React page - so it is there for crawlers that
 * don't run JavaScript and still there if the SSR service is down. Passed to
 * the layout with `Inertia::render(...)->withViewData(['seo' => $meta])`.
 */
final readonly class PageMeta
{
    /**
     * @param  list<array<string, mixed>>  $structuredData  schema.org JSON-LD documents
     */
    public function __construct(
        public string $title,
        public string $description,
        public array $structuredData = [],
    ) {}

    /** Same shape as the page's own <Head title>, so the two never disagree. */
    public function documentTitle(): string
    {
        return $this->title.' - '.config('app.name');
    }

    /** No query string or fragment, so tracking parameters don't spawn duplicate URLs. */
    public function canonicalUrl(): string
    {
        return url()->current();
    }

    /** Square - fits a summary card; a 1200x630 image would let it be a large one. */
    public function imageUrl(): string
    {
        return asset('apple-touch-icon.png');
    }
}
