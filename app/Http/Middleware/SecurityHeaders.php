<?php

namespace App\Http\Middleware;

use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser-hardening headers on every web response, including the
 * Content-Security-Policy (config/security.php). HSTS is only sent over https
 * (and so only in production behind Railway's TLS edge). Attachments already
 * carry their own strict CSP (Attachments.php), which this never overrides.
 *
 * The policy is nonce-based: Vite's tags and the inline scripts in
 * resources/views/app.blade.php carry this request's nonce, and nothing else
 * inline may run. Styles allow 'unsafe-inline' - the UI libraries (Radix,
 * dnd-kit, Sonner) set style attributes at runtime and script injection
 * through CSS is a far smaller risk than through scripts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before the view renders, so @vite and the inline scripts get it.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        $mode = config('security.csp');

        if ($mode !== 'off') {
            $headers[$mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy']
                = $this->policy($nonce, $request->isSecure(), $request->getHttpHost());
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function policy(string $nonce, bool $secure, string $host): string
    {
        $self = ["'self'"];
        $scripts = [...$self, "'nonce-{$nonce}'"];
        $styles = [...$self, "'unsafe-inline'", 'https://fonts.bunny.net'];
        $connect = $self;

        // The Vite dev server (hot module reload) serves scripts and styles
        // from its own origin and talks to the page over a WebSocket.
        if (Vite::isRunningHot()) {
            $origin = rtrim(trim(file_get_contents(public_path('hot'))), '/');
            $socket = preg_replace('#^http#', 'ws', $origin);
            array_push($scripts, $origin);
            array_push($styles, $origin);
            array_push($connect, $origin, $socket);
        }

        $reverb = config('security.reverb');

        if (filled($reverb['host'])) {
            $connect[] = ($reverb['scheme'] === 'http' ? 'ws' : 'wss').'://'.$reverb['host'].(filled($reverb['port']) ? ':'.$reverb['port'] : '');
        }

        // Self-hosted: the browser connects to this site's own origin, where
        // the reverse proxy forwards /app/* to Reverb (resources/js/echo.ts).
        // Older Safari doesn't count 'self' as covering ws:/wss:, so say it.
        if (Edition::selfHosted()) {
            $connect[] = ($secure ? 'wss' : 'ws').'://'.$host;
        }

        $directives = [
            'default-src' => $self,
            'script-src' => $scripts,
            'style-src' => $styles,
            // Photos and logos come from this site or, on S3, from signed
            // https URLs; data:/blob: cover inline previews before upload.
            'img-src' => [...$self, 'data:', 'blob:', 'https:'],
            'font-src' => [...$self, 'data:', 'https://fonts.bunny.net'],
            'connect-src' => $connect,
            'media-src' => [...$self, 'blob:', 'https:'],
            'frame-src' => [...$self, 'blob:'],
            'object-src' => $self,
            'worker-src' => [...$self, 'blob:'],
            'manifest-src' => $self,
            'base-uri' => $self,
            'form-action' => $self,
            'frame-ancestors' => $self,
        ];

        $policy = collect($directives)->map(fn (array $sources, string $name) => $name.' '.implode(' ', $sources))->implode('; ');

        return $secure ? $policy.'; upgrade-insecure-requests' : $policy;
    }
}
