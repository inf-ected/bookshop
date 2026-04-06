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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Книги
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
            <a
                href="{{ route('admin.posts.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 border border-border-subtle bg-surface rounded-lg text-sm text-text-primary hover:bg-surface-muted hover:border-brand-300 transition"
            >
                <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Статьи
            </a>
            <a
                href="{{ route('admin.orders.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 border border-border-subtle bg-surface rounded-lg text-sm text-text-primary hover:bg-surface-muted hover:border-brand-300 transition"
            >
                <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Заказы
            </a>
            <a
                href="{{ route('admin.users.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 border border-border-subtle bg-surface rounded-lg text-sm text-text-primary hover:bg-surface-muted hover:border-brand-300 transition"
            >
                <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Пользователи
            </a>
            <a
                href="{{ route('admin.download-logs.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 border border-border-subtle bg-surface rounded-lg text-sm text-text-primary hover:bg-surface-muted hover:border-brand-300 transition"
            >
                <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Скачивания
            </a>
            <a
                href="{{ route('admin.newsletter.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 border border-border-subtle bg-surface rounded-lg text-sm text-text-primary hover:bg-surface-muted hover:border-brand-300 transition"
            >
                <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Рассылка
            </a>
        </div>
    </div>

</div>

@endsection
