@props([
    'title',
    'description' => null,
    'canonical' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'jsonLd' => null,
])

@php
    $canonical ??= request()->url();
    $siteName = 'Книжная лавка';
    $fullTitle = $title . ' — ' . $siteName;
    $twitterCard = $ogImage ? 'summary_large_image' : 'summary';
@endphp

@push('head')
<title>{{ $fullTitle }}</title>

@if($description)
<meta name="description" content="{{ $description }}">
@endif

<link rel="canonical" href="{{ $canonical }}">

{{-- OpenGraph --}}
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:type" content="{{ $ogType }}">
@if($description)
<meta property="og:description" content="{{ $description }}">
@endif
@if($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
@endif

{{-- Twitter Card --}}
<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $fullTitle }}">
@if($description)
<meta name="twitter:description" content="{{ $description }}">
@endif
@if($ogImage)
<meta name="twitter:image" content="{{ $ogImage }}">
@endif

{{-- JSON-LD --}}
@if($jsonLd)
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG) !!}</script>
@endif
@endpush
