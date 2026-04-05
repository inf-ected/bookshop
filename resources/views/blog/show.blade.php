@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto px-4 py-10">

    {{-- Breadcrumb --}}
    <nav class="text-xs text-text-subtle mb-6 font-sans">
        <a href="{{ route('blog.index') }}" class="hover:text-text-muted transition">Блог</a>
        <span class="mx-2">/</span>
        <span>{{ $post->title }}</span>
    </nav>

    <article>

        {{-- Cover image --}}
        @if($post->cover_url)
            <div class="mb-8 rounded-lg overflow-hidden">
                <img
                    src="{{ $post->cover_url }}"
                    alt="{{ $post->title }}"
                    class="w-full object-cover max-h-96"
                >
            </div>
        @endif

        {{-- Title --}}
        <h1 class="font-serif text-3xl md:text-4xl leading-tight text-text-primary mb-3">
            {{ $post->title }}
        </h1>

        {{-- Published date --}}
        <time
            datetime="{{ $post->published_at->toDateString() }}"
            class="block text-sm text-text-subtle font-sans mb-8"
        >
            {{ $post->published_at->translatedFormat('d F Y') }}
        </time>

        {{-- Body --}}
        <div class="font-serif text-base leading-relaxed text-text-primary prose max-w-none">
            {!! $post->body !!}
        </div>

    </article>

    {{-- Back link --}}
    <div class="mt-12 border-t border-border-subtle pt-6">
        <a
            href="{{ route('blog.index') }}"
            class="font-sans text-sm text-brand-700 hover:text-brand-800 transition"
        >
            ← Назад в блог
        </a>
    </div>

</div>

@endsection
