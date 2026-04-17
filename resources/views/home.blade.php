@extends('layouts.app')

<x-seo
    title="{{ __('home.title') }}"
    description="{{ __('home.description') }}"
    og-type="website"
/>

@section('content')

{{-- Hero banner --}}
<section class="bg-brand-950 text-white py-14 px-4">
    <div class="max-w-5xl mx-auto text-center">
        <h1 class="font-serif text-3xl md:text-5xl leading-tight mb-4">
            {{ __('home.title') }}
        </h1>
        <p class="font-sans text-brand-200 text-base md:text-lg mb-8 max-w-xl mx-auto">
            {{ __('home.description') }}
        </p>
        <a href="{{ route('books.index') }}" class="inline-block px-8 py-3 bg-accent text-white font-sans text-sm rounded hover:bg-accent-dark transition">
            {{ __('home.To the Catalog') }}
        </a>
    </div>
</section>

{{-- Featured books carousel --}}
<section class="py-12 px-4">
    <div class="max-w-5xl mx-auto">

        <div class="flex items-center justify-between mb-6">
            <h2 class="font-serif text-2xl text-text-primary">{{ __('home.Books') }}</h2>
            <a href="{{ route('books.index') }}" class="text-sm font-sans text-brand-700 hover:text-brand-900 transition">
               {{ __('home.All Books') }} 
            </a>
        </div>

        @if($books->isEmpty())
            <div class="py-16 text-center">
                <p class="font-serif text-xl text-text-muted">{{ __('home.Books will appear here soon') }}</p>
                <p class="text-sm text-text-subtle mt-2">{{ ('stay tuned') }}</p>
            </div>
        @else
            {{-- Carousel wrapper --}}
            <div
                x-data="{
                    active: 0,
                    total: {{ $books->count() }},
                    updateActive(el) {
                        const items = el.querySelectorAll('[data-slide]');
                        const scrollLeft = el.scrollLeft;
                        const itemWidth = items[0]?.offsetWidth + 16;
                        this.active = Math.round(scrollLeft / itemWidth);
                    }
                }"
            >
                <div
                    class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-4 -mx-4 px-4 md:mx-0 md:px-0"
                    style="scrollbar-width: none; -ms-overflow-style: none;"
                    @scroll.passive="updateActive($el)"
                >
                    @foreach($books as $i => $book)
                        <div class="snap-start shrink-0 w-44 md:w-52" data-slide="{{ $i }}">
                            <x-book-card :book="$book" :owned-book-ids="$ownedBookIds" />
                        </div>
                    @endforeach
                </div>

                {{-- Dot indicators --}}
                @if($books->count() > 1)
                    <div class="flex gap-2 justify-center mt-4">
                        @foreach($books as $i => $book)
                            <button
                                type="button"
                                class="w-2 h-2 rounded-full transition"
                                :class="active === {{ $i }} ? 'bg-brand-700' : 'bg-border-subtle'"
                                aria-label="{{ __('home.Slide') }} {{ $i + 1 }}"
                            ></button>
                        @endforeach
                    </div>
                @endif

            </div>
        @endif

    </div>
</section>

{{-- Blog preview --}}
@if($posts->isNotEmpty())
<section class="py-12 px-4 bg-surface border-t border-border-subtle">
    <div class="max-w-5xl mx-auto">

        <div class="flex items-center justify-between mb-8">
            <h2 class="font-serif text-2xl text-text-primary">Блог</h2>
            <a href="{{ route('blog.index') }}" class="text-sm font-sans text-brand-700 hover:text-brand-800 transition-colors">
               {{ __('home.All Posts') }} 
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            @foreach($posts as $post)
            <a href="{{ route('blog.show', $post) }}" class="group flex flex-col rounded-xl overflow-hidden border border-border-subtle bg-surface hover:shadow-md transition-shadow">
                @if($post->cover_url)
                    <div class="aspect-[16/9] overflow-hidden bg-surface-muted">
                        <img src="{{ $post->cover_url }}" alt="{{ $post->title }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    </div>
                @else
                    <div class="aspect-[16/9] bg-brand-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                        </svg>
                    </div>
                @endif
                <div class="p-4 flex flex-col flex-1 gap-2">
                    <p class="text-xs text-text-subtle font-sans">{{ $post->published_at?->translatedFormat('d F Y') }}</p>
                    <h3 class="font-serif text-base text-text-primary leading-snug group-hover:text-brand-700 transition-colors">{{ $post->title }}</h3>
                    @if($post->excerpt)
                        <p class="text-sm text-text-muted leading-relaxed line-clamp-2 flex-1">{{ $post->excerpt }}</p>
                    @endif
                    <span class="mt-1 text-xs font-sans text-brand-700 font-medium">Читать &rarr;</span>
                </div>
            </a>
            @endforeach
        </div>

    </div>
</section>
@endif

{{-- Static promo strip --}}
<section class="bg-surface-muted border-t border-border-subtle py-10 px-4">
    <div class="max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6 text-center">

        <div class="space-y-2">
            <div class="text-2xl font-serif text-brand-700">📖</div>
            <h3 class="font-serif text-base">{{ __('home.Instant Access') }}</h3>
            <p class="text-sm text-text-muted">{{ __('home.Instant Access Message') }}</p>
        </div>

        <div class="space-y-2">
            <div class="text-2xl font-serif text-brand-700">🔒</div>
            <h3 class="font-serif text-base">{{ __('home.Secure Payment') }}</h3>
            <p class="text-sm text-text-muted">{{ __('home.Secure Payment Message') }}</p>
        </div>

        <div class="space-y-2">
            <div class="text-2xl font-serif text-brand-700">♾️</div>
            <h3 class="font-serif text-base">{{ __('home.Forever Yours') }}</h3>
            <p class="text-sm text-text-muted">{{ __('home.Forever Yours Message') }}</p>
        </div>

    </div>
</section>

@endsection
