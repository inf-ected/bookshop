@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="font-serif text-3xl text-text-primary">Моя библиотека</h1>
    </div>

    {{-- Cabinet tab navigation --}}
    <x-cabinet-nav />

    @if($userBooks->isEmpty())

        {{-- Empty state --}}
        <div class="bg-white border border-border-subtle rounded-xl p-12 text-center">
            <div class="mx-auto w-16 h-16 bg-surface-muted rounded-full flex items-center justify-center mb-5">
                <svg class="w-8 h-8 text-text-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <p class="font-serif text-xl text-text-primary mb-2">У вас пока нет книг</p>
            <p class="text-sm text-text-muted mb-7">Приобретите книги из каталога, чтобы они появились здесь.</p>
            <a href="{{ route('books.index') }}"
               class="inline-block px-6 py-2.5 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition">
                Перейти в каталог
            </a>
        </div>

    @else

        {{-- Books grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            @foreach($userBooks as $userBook)
                <div class="flex flex-col bg-white rounded-lg border border-border-subtle overflow-hidden shadow-sm hover:shadow-md transition-shadow">

                    {{-- Cover --}}
                    <a href="{{ route('books.show', $userBook->book) }}" class="block aspect-[2/3] overflow-hidden bg-surface-muted">
                        @if($userBook->book->cover_thumb_url)
                            <img
                                src="{{ $userBook->book->cover_thumb_url }}"
                                alt="{{ $userBook->book->title }}"
                                class="w-full h-full object-cover"
                                loading="lazy"
                            >
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-brand-100">
                                <span class="font-serif text-2xl text-brand-700 text-center px-3 leading-tight">
                                    {{ mb_substr($userBook->book->title, 0, 2) }}
                                </span>
                            </div>
                        @endif
                    </a>

                    {{-- Info --}}
                    <div class="p-4 flex flex-col flex-1 gap-2">
                        <a href="{{ route('books.show', $userBook->book) }}" class="block">
                            <h3 class="font-serif text-base leading-snug text-text-primary hover:text-brand-700 transition line-clamp-3">
                                {{ $userBook->book->title }}
                            </h3>
                        </a>

                        {{-- Download button --}}
                        <div class="mt-auto pt-3">
                            <a
                                href="{{ route('books.download', $userBook->book) }}"
                                class="flex items-center justify-center gap-1.5 w-full px-3 py-2 bg-brand-700 text-white font-sans text-xs rounded hover:bg-brand-800 transition"
                                onclick="if(typeof gtag !== 'undefined') gtag('event', 'file_download', { file_name: {{ Js::from($userBook->book->title) }}, item_id: '{{ $userBook->book->id }}' })"
                            >
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Скачать
                            </a>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>

    @endif

</div>

@endsection
