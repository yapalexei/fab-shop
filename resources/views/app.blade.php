<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Sets the `dark` class before first paint, synchronously - if this
        waited for resources/js/lib/theme.ts to run after React mounts, the
        page would flash light-then-dark on every load for a dark-mode user. --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                try {
                    var stored = localStorage.getItem('theme');
                    if (stored === 'dark') {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>

        <!-- Favicons -->
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes(null, Vite::cspNonce())
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead

        {{-- A server-rendered page's head (above) already carries its own
        <title>; this one is for client-rendered pages and for when the SSR
        service is down. Two <title>s would leave the browser and crawlers
        with the first, wrong one. --}}
        @if (empty($__inertiaSsrResponse))
            <title inertia>{{ isset($seo) ? $seo->documentTitle() : config('app.name', 'Laravel') }}</title>
        @endif

        @isset($seo)
            @include('partials.seo', ['seo' => $seo])
        @endisset
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
