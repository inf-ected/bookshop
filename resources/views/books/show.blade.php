@extends('layouts.app')

@section('content')

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
            @if($book->cover_path)
                <img
                    src="{{ asset('storage/' . $book->cover_path) }}"
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
                <span class="font-sans text-2xl font-bold text-accent">
                    {{ number_format($book->price / 100, 0, ',', ' ') }}&nbsp;₽
                </span>

                {{-- Cart — disabled until Phase 5 --}}
                <button
                    type="button"
                    disabled
                    class="px-6 py-2.5 bg-surface-muted text-text-subtle border border-border-subtle rounded font-sans text-sm cursor-not-allowed"
                >
                    Добавить в корзину
                </button>

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
