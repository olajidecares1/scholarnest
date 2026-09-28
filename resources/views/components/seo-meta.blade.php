{{--
    Search and link-preview metadata, in one place.

    The <title> stays with each page; this adds what goes around it: the
    description, the one canonical address, an explicit robots directive when
    a page must stay out of search, Open Graph and Twitter cards for link
    previews, and JSON-LD structured data.

    Only real data goes in. A description or image that is null is left out
    rather than filled with something made up, and structured data describes
    what the page actually shows, nothing more.
--}}
@props([
    'title' => null,
    'description' => null,
    // The one address this page should be known by. Null = the current URL
    // without its query string, which is right for almost every page.
    'canonical' => null,
    // e.g. 'noindex, follow'. Null means indexable (no tag at all).
    'robots' => null,
    'image' => null,
    'imageAlt' => null,
    'type' => 'website',
    'siteName' => null,
    'locale' => 'en_NG',
    // One schema.org object, or a list of them.
    'jsonLd' => null,
])

@php
    $canonicalUrl = $canonical ?: url()->current();
    $cleanDescription = $description
        ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $description))), 160)
        : null;
    // Crawlers and link previews need an absolute address.
    $image = $image ? url($image) : null;
    $siteLabel = $siteName ?: config('app.name', 'AkademicNest');
    $jsonLdItems = $jsonLd === null ? [] : (array_is_list($jsonLd) ? $jsonLd : [$jsonLd]);
@endphp

@if ($cleanDescription)
    <meta name="description" content="{{ $cleanDescription }}">
@endif
@if ($robots)
    <meta name="robots" content="{{ $robots }}">
@else
    <link rel="canonical" href="{{ $canonicalUrl }}">
@endif

@unless ($robots && str_contains($robots, 'noindex'))
    <meta property="og:site_name" content="{{ $siteLabel }}">
    <meta property="og:type" content="{{ $type }}">
    @if ($title)
        <meta property="og:title" content="{{ $title }}">
    @endif
    @if ($cleanDescription)
        <meta property="og:description" content="{{ $cleanDescription }}">
    @endif
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:locale" content="{{ $locale }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
        @if ($imageAlt)
            <meta property="og:image:alt" content="{{ $imageAlt }}">
        @endif
    @endif
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    @if ($title)
        <meta name="twitter:title" content="{{ $title }}">
    @endif
    @if ($cleanDescription)
        <meta name="twitter:description" content="{{ $cleanDescription }}">
    @endif
    @if ($image)
        <meta name="twitter:image" content="{{ $image }}">
    @endif

    @foreach ($jsonLdItems as $item)
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org'] + array_filter($item, fn ($v) => $v !== null && $v !== '' && $v !== []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
@endunless
