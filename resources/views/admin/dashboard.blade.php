@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="font-serif text-2xl text-text-primary">Панель управления</h1>
            <p class="text-sm text-text-muted mt-1">Обзор состояния каталога</p>
        </div>
        <a
            href="{{ route('admin.books.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-700 hover:bg-brand-900 text-white text-sm font-sans font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            Управление книгами
        </a>
    </div>

    {{-- Flash success --}}
    @if (session('success'))
        <div class="mb-6 px-4 py-3 bg-success-light border border-success-border rounded-lg text-sm text-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stats grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">

        {{-- Total books --}}
        <div class="bg-surface border border-border-subtle rounded-xl p-6 space-y-1">
            <p class="text-xs font-sans font-medium text-text-muted uppercase tracking-widest">Всего книг</p>
            <p class="font-serif text-4xl text-text-primary">{{ $stats['total_books'] }}</p>
            <a href="{{ route('admin.books.index') }}" class="text-xs text-brand-600 hover:text-brand-800 transition">
                Все книги &rarr;
            </a>
        </div>

        {{-- Published --}}
        <div class="bg-surface border border-border-subtle rounded-xl p-6 space-y-1">
            <p class="text-xs font-sans font-medium text-text-muted uppercase tracking-widest">Опубликовано</p>
            <p class="font-serif text-4xl text-success">{{ $stats['published_books'] }}</p>
            <p class="text-xs text-text-subtle">доступны в каталоге</p>
        </div>

        {{-- Drafts --}}
        <div class="bg-surface border border-border-subtle rounded-xl p-6 space-y-1">
            <p class="text-xs font-sans font-medium text-text-muted uppercase tracking-widest">Черновики</p>
            <p class="font-serif text-4xl text-text-muted">{{ $stats['draft_books'] }}</p>
            <p class="text-xs text-text-subtle">не опубликованы</p>
        </div>

        {{-- Featured --}}
        <div class="bg-surface border border-border-subtle rounded-xl p-6 space-y-1">
            <p class="text-xs font-sans font-medium text-text-muted uppercase tracking-widest">В избранном</p>
            <p class="font-serif text-4xl text-brand-700">{{ $stats['featured_books'] ?? '—' }}</p>
            <p class="text-xs text-text-subtle">показываются на главной</p>
        </div>

    </div>

    {{-- Quick nav --}}
    <div class="bg-surface-muted border border-border-subtle rounded-xl p-6">
        <h2 class="text-sm font-sans font-semibold text-text-primary mb-4">Быстрые действия</h2>
        <div class="flex flex-wrap gap-3">
            <a
                href="{{ route('admin.books.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 border border-border-subtle bg-surface rounded-lg text-sm text-text-primary hover:bg-surface-muted hover:border-brand-300 transition"
            >
                <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Список книг
            </a>
            <a
                href="{{ route('admin.books.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 border border-brand-300 bg-brand-50 rounded-lg text-sm text-brand-700 hover:bg-brand-100 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Добавить книгу
            </a>
        </div>
    </div>

</div>

@endsection
