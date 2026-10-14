<?php

namespace App\Support\Ssr;

use App\Support\PublicSsr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;
use Throwable;

/**
 * Inertia's HTTP gateway, narrowed to the public pages (PublicSsr) and made
 * safe to leave on when the renderer is slow or down: a short timeout, so a
 * hung Node process delays a page by seconds, never holds it for the
 * stock 30s, and a log line (at most one a minute) so a dead renderer - which
 * silently drops the site back to client rendering, and so quietly costs SEO -
 * doesn't go unnoticed.
 */
class PublicPagesGateway extends HttpGateway
{
    private const CONNECT_TIMEOUT_SECONDS = 1;

    private const TIMEOUT_SECONDS = 3;

    protected function shouldDispatch(): bool
    {
        return parent::shouldDispatch() && PublicSsr::appliesTo();
    }

    public function dispatch(array $page): ?Response
    {
        if (! $this->shouldDispatch()) {
            return null;
        }

        try {
            $rendered = Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($this->getUrl('/render'), $page)
                ->throw()
                ->json();
        } catch (Throwable $e) {
            $this->reportFailure($e);

            return null;
        }

        if (! is_array($rendered) || ! isset($rendered['head'], $rendered['body'])) {
            return null;
        }

        return new Response(implode("\n", $rendered['head']), $rendered['body']);
    }

    private function reportFailure(Throwable $e): void
    {
        if (Cache::add('inertia-ssr-failure-logged', true, 60)) {
            Log::warning('Inertia SSR unavailable; serving the page client-rendered.', [
                'url' => $this->getUrl('/render'),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
