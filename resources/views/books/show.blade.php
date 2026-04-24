@extends('layouts.app')

@php
    $bookDescription = $book->annotation ? mb_substr(strip_tags($book->annotation), 0, 160) : null;
    $bookJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $book->title,
        'offers' => [
            '@type' => 'Offer',
            'price' => number_format($book->price / 100, 2, '.', ''),
            'priceCurrency' => config('shop.currency_code'),
            'availability' => 'https://schema.org/InStock',
        ],
    ];
    if ($bookDescription) {
        $bookJsonLd['description'] = $bookDescription;
    }
    if ($book->cover_url) {
        $bookJsonLd['image'] = $book->cover_url;
    }
@endphp

<x-seo
    :title="$book->title"
    :description="$bookDescription"
    :canonical="route('books.show', $book)"
    :og-image="$book->cover_url"
    og-type="product"
    :json-ld="$bookJsonLd"
/>

@section('content')

@if($book->isAdult())
    <x-adult-content-gate />
@endif

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Breadcrumb --}}
    <nav class="text-xs text-text-subtle mb-6 font-sans">
        <a href="{{ route('books.index') }}" class="hover:text-text-muted transition">Каталог</a>
        <span class="mx-2">/</span>
        <span>{{ $book->title }}</span>
    </nav>

    <div class="flex flex-col md:flex-row gap-10">

        {{-- Cover --}}
        <div class="shrink-0 w-full md:w-64">
            @if($book->cover_url)
                <img
                    src="{{ $book->cover_url }}"
                    alt="{{ $book->title }}"
                    class="w-full md:w-64 rounded-lg shadow-md object-cover"
                >
            @else
                <div class="w-full md:w-64 aspect-[2/3] rounded-lg bg-brand-100 flex items-center justify-center">
                    <span class="font-serif text-4xl text-brand-700">
                        {{ mb_substr($book->title, 0, 2) }}
                    </span>
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div class="flex-1 flex flex-col gap-5">

            <div>
                <h1 class="font-serif text-3xl md:text-4xl leading-tight text-text-primary">
                    {{ $book->title }}
                </h1>
            </div>

            {{-- Price + CTA --}}
            <div class="flex items-center gap-4 flex-wrap">
                <span class="font-sans text-2xl font-bold text-brand-700">
                    {{ number_format($book->price / 100, config('shop.currency_decimals'), config('shop.currency_decimal_sep'), ' ') }}&nbsp;{{ config('shop.currency_symbol') }}
                </span>

                {{-- Cart / library CTA — hidden for admins --}}
                @auth
                    @unless(auth()->user()->isAdmin())
                        @if($isOwned)
                            @if($readyClientFiles->isNotEmpty())
                                @foreach($readyClientFiles as $bookFile)
                                    <a
                                        href="{{ route('books.download', [$book, 'format' => $bookFile->format->value]) }}"
                                        class="px-5 py-2.5 bg-brand-700 text-white rounded font-sans text-sm font-semibold hover:bg-brand-800 transition flex items-center gap-1.5"
                                        onclick="if(typeof gtag !== 'undefined') gtag('event', 'file_download', { file_name: {{ Js::from($book->title) }}, item_id: '{{ $book->id }}', format: '{{ $bookFile->format->value }}' })"
                                    >
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        {{ $bookFile->format->label() }}
                                    </a>
                                @endforeach
                            @else
                                <a
                                    href="{{ route('cabinet.library') }}"
                                    class="px-6 py-2.5 border border-success-border text-success bg-success-light rounded font-sans text-sm font-semibold"
                                >
                                    В библиотеке
                                </a>
                            @endif
                        @else
                            <form method="POST" action="{{ route('cart.store', $book) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="px-6 py-2.5 bg-brand-700 text-white rounded font-sans text-sm hover:bg-brand-800 transition font-semibold"
                                >
                                    В корзину
                                </button>
                            </form>
                        @endif
                    @endunless
                @else
                    <a
                        href="{{ route('login') }}"
                        class="px-6 py-2.5 bg-brand-700 text-white rounded font-sans text-sm hover:bg-brand-800 transition font-semibold"
                    >
                        В корзину
                    </a>
                @endauth

                @if($book->fragment)
                    <a
                        href="{{ route('books.fragment', $book) }}"
                        class="px-6 py-2.5 border border-brand-700 text-brand-700 rounded font-sans text-sm hover:bg-brand-50 transition"
                    >
                        Читать фрагмент
                    </a>
                @endif
            </div>

            {{-- Annotation --}}
            @if($book->annotation)
                <div class="border-t border-border-subtle pt-5">
                    <h2 class="font-sans text-xs font-semibold uppercase tracking-widest text-text-subtle mb-3">
                        Аннотация
                    </h2>
                    <div class="font-serif text-base leading-relaxed text-text-primary prose max-w-none">
                        {!! nl2br(e($book->annotation)) !!}
                    </div>
                </div>
            @endif

            {{-- Excerpt --}}
            @if($book->excerpt)
                <div class="border-t border-border-subtle pt-5">
                    <h2 class="font-sans text-xs font-semibold uppercase tracking-widest text-text-subtle mb-3">
                        Отрывок
                    </h2>
                    <div class="font-serif text-base leading-relaxed text-text-primary bg-surface-muted rounded p-4 italic">
                        &laquo;{{ $book->excerpt }}&raquo;
                    </div>
                </div>
            @endif

        </div>
    </div>

</div>

@endsection
