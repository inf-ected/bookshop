@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="font-serif text-2xl text-text-primary">Логи скачиваний</h1>
            <p class="text-sm text-text-muted mt-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700 transition">Панель управления</a>
                &rsaquo; Логи скачиваний
            </p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.download-logs.index') }}" class="mb-5 flex flex-wrap gap-3">
        <input
            type="number"
            name="user_id"
            value="{{ $userId }}"
            placeholder="ID пользователя"
            min="1"
            class="w-44 px-3.5 py-2.5 rounded-lg border border-border-subtle text-sm text-text-primary bg-surface placeholder:text-text-subtle focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
        >
        <input
            type="number"
            name="book_id"
            value="{{ $bookId }}"
            placeholder="ID книги"
            min="1"
            class="w-36 px-3.5 py-2.5 rounded-lg border border-border-subtle text-sm text-text-primary bg-surface placeholder:text-text-subtle focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
        >
        <button
            type="submit"
            class="px-4 py-2.5 bg-brand-700 hover:bg-brand-900 text-white text-sm font-medium rounded-lg transition"
        >
            Фильтровать
        </button>
        @if ($userId || $bookId)
            <a
                href="{{ route('admin.download-logs.index') }}"
                class="px-4 py-2.5 border border-border-subtle text-sm text-text-muted rounded-lg hover:bg-surface-muted transition"
            >
                Сбросить
            </a>
        @endif
    </form>

    @if ($logs->isEmpty())

        <div class="bg-surface border border-border-subtle rounded-xl py-16 text-center">
            <svg class="w-12 h-12 text-text-subtle mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <p class="font-serif text-lg text-text-muted">Скачиваний не найдено</p>
        </div>

    @else

        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm font-sans">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-muted">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider">Пользователь</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider">Книга</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-40">Скачано</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-36">IP-адрес</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach ($logs as $log)
                            <tr class="hover:bg-surface-muted transition">

                                <td class="px-4 py-3">
                                    @if ($log->user)
                                        <a href="{{ route('admin.users.show', $log->user) }}" class="font-medium text-text-primary hover:text-brand-700 transition">
                                            {{ $log->user->name }}
                                        </a>
                                        <p class="text-xs text-text-subtle mt-0.5">{{ $log->user->email }}</p>
                                    @else
                                        <span class="text-text-subtle text-xs">Пользователь удалён</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    @if ($log->book)
                                        <a href="{{ route('admin.books.edit', $log->book) }}" class="font-medium text-text-primary hover:text-brand-700 transition">
                                            {{ $log->book->title }}
                                        </a>
                                    @else
                                        <span class="text-text-subtle text-xs">Книга удалена</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-text-muted text-xs">
                                    {{ $log->downloaded_at->format('d.m.Y H:i') }}
                                </td>

                                <td class="px-4 py-3 text-text-muted font-mono text-xs">
                                    {{ $log->ip_address ?? '—' }}
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($logs->hasPages())
            <div class="mt-6">
                {{ $logs->links() }}
            </div>
        @endif

    @endif

</div>

@endsection
