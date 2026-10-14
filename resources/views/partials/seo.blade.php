{{-- See App\Support\PageMeta. --}}
<meta name="description" content="{{ $seo->description }}">
<link rel="canonical" href="{{ $seo->canonicalUrl() }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $seo->documentTitle() }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonicalUrl() }}">
<meta property="og:image" content="{{ $seo->imageUrl() }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $seo->documentTitle() }}">
<meta name="twitter:description" content="{{ $seo->description }}">
<meta name="twitter:image" content="{{ $seo->imageUrl() }}">

@foreach ($seo->structuredData as $document)
    {{-- A data block, not executed, but it carries the nonce like every inline script. --}}
    <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
