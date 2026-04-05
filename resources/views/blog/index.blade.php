@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    <div class="mb-8">
        <h1 class="font-serif text-3xl text-text-primary">Блог</h1>
        <p class="text-text-muted mt-1 text-sm">Статьи, новости и обзоры</p>
    </div>

    @if($posts->isEmpty())
        <div class="py-20 text-center">
            <p class="font-serif text-xl text-text-muted">Публикаций пока нет</p>
            <p class="text-sm text-text-subtle mt-2">Загляните позже</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($posts as $post)
                <article class="flex flex-col bg-white rounded-lg border border-border-subtle overflow-hidden shadow-sm hover:shadow-md transition-shadow">

                    {{-- Cover image --}}
                    <a href="{{ route('blog.show', $post) }}" class="block overflow-hidden bg-surface-muted aspect-[16/9]">
                        @if($post->cover_thumb_url)
                            <img
                                src="{{ $post->cover_thumb_url }}"
                                alt="{{ $post->title }}"
                                class="w-full h-full object-cover"
                                loading="lazy"
                            >
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-brand-100">
                                <span class="font-serif text-3xl text-brand-700">
                                    {{ mb_substr($post->title, 0, 2) }}
                                </span>
                            </div>
                        @endif
                    </a>

                    {{-- Content --}}
                    <div class="p-5 flex flex-col flex-1 gap-3">

                        <div>
                            <a href="{{ route('blog.show', $post) }}" class="block">
                                <h2 class="font-serif text-xl leading-snug text-text-primary hover:text-brand-700 transition">
                                    {{ $post->title }}
                                </h2>
                            </a>

                            @if($post->excerpt)
                                <p class="text-text-muted text-sm leading-relaxed mt-2 line-clamp-3 font-sans">
                                    {{ $post->excerpt }}
                                </p>
                            @endif
                        </div>

                        <div class="mt-auto flex items-center justify-between pt-3 border-t border-border-subtle">
                            <time
                                datetime="{{ $post->published_at->toDateString() }}"
                                class="text-xs text-text-subtle font-sans"
                            >
                                {{ $post->published_at->translatedFormat('d F Y') }}
                            </time>

                            <a
                                href="{{ route('blog.show', $post) }}"
                                class="text-xs font-sans font-semibold text-brand-700 hover:text-brand-800 transition"
                            >
                                Читать →
                            </a>
                        </div>

                    </div>

                </article>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($posts->hasPages())
            <div class="mt-10">
                {{ $posts->links() }}
            </div>
        @endif
    @endif

</div>

@endsection
