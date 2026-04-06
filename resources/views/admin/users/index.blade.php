@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="font-serif text-2xl text-text-primary">Пользователи</h1>
            <p class="text-sm text-text-muted mt-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700 transition">Панель управления</a>
                &rsaquo; Пользователи
            </p>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-5 px-4 py-3 bg-success-light border border-success-border rounded-lg text-sm text-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-5 px-4 py-3 bg-error-light border border-error-border rounded-lg text-sm text-error">
            {{ session('error') }}
        </div>
    @endif

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-5">
        <div class="flex gap-3">
            <input
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="Поиск по имени или email..."
                class="flex-1 px-3.5 py-2.5 rounded-lg border border-border-subtle text-sm text-text-primary bg-surface placeholder:text-text-subtle focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
            >
            <button
                type="submit"
                class="px-4 py-2.5 bg-brand-700 hover:bg-brand-900 text-white text-sm font-medium rounded-lg transition"
            >
                Найти
            </button>
            @if ($search !== '')
                <a
                    href="{{ route('admin.users.index') }}"
                    class="px-4 py-2.5 border border-border-subtle text-sm text-text-muted rounded-lg hover:bg-surface-muted transition"
                >
                    Сбросить
                </a>
            @endif
        </div>
    </form>

    @if ($users->isEmpty())

        <div class="bg-surface border border-border-subtle rounded-xl py-16 text-center">
            <svg class="w-12 h-12 text-text-subtle mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <p class="font-serif text-lg text-text-muted">Пользователи не найдены</p>
        </div>

    @else

        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm font-sans">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-muted">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-14">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider">Пользователь</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-24">Роль</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-24">Статус</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-40">Зарегистрирован</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-text-muted uppercase tracking-wider w-20">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach ($users as $user)
                            <tr class="hover:bg-surface-muted transition">

                                <td class="px-4 py-3 text-text-muted font-mono text-xs">
                                    {{ $user->id }}
                                </td>

                                <td class="px-4 py-3">
                                    <p class="font-medium text-text-primary">{{ $user->name }}</p>
                                    <p class="text-xs text-text-subtle mt-0.5">{{ $user->email }}</p>
                                </td>

                                {{-- Role badge --}}
                                <td class="px-4 py-3">
                                    @if ($user->isAdmin())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-brand-100 text-brand-700">
                                            Админ
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-muted text-text-muted">
                                            Пользователь
                                        </span>
                                    @endif
                                </td>

                                {{-- Banned badge --}}
                                <td class="px-4 py-3">
                                    @if ($user->isBanned())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-error-light text-error">
                                            <span class="w-1.5 h-1.5 rounded-full bg-error shrink-0"></span>
                                            Заблокирован
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-success-light text-success">
                                            <span class="w-1.5 h-1.5 rounded-full bg-success-dot shrink-0"></span>
                                            Активен
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-text-muted text-xs">
                                    {{ $user->created_at->format('d.m.Y') }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <a
                                        href="{{ route('admin.users.show', $user) }}"
                                        class="p-1.5 inline-flex text-text-muted hover:text-brand-700 hover:bg-brand-50 rounded-lg transition"
                                        aria-label="Просмотреть"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($users->hasPages())
            <div class="mt-6">
                {{ $users->links() }}
            </div>
        @endif

    @endif

</div>

@endsection
