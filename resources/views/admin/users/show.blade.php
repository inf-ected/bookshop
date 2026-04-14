@extends('layouts.app')

@section('content')

<div class="max-w-4xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="mb-8">
        <h1 class="font-serif text-2xl text-text-primary">{{ $user->name }}</h1>
        <p class="text-sm text-text-muted mt-1">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700 transition">Панель управления</a>
            &rsaquo;
            <a href="{{ route('admin.users.index') }}" class="hover:text-brand-700 transition">Пользователи</a>
            &rsaquo; {{ $user->name }}
        </p>
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

    <div class="space-y-5">

        {{-- Profile info --}}
        <div class="bg-surface border border-border-subtle rounded-xl p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-3">
                    <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest mb-3">Профиль</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                        <div>
                            <p class="text-text-subtle text-xs mb-0.5">ID</p>
                            <p class="font-mono text-text-primary">{{ $user->id }}</p>
                        </div>
                        <div>
                            <p class="text-text-subtle text-xs mb-0.5">Email</p>
                            <p class="text-text-primary">{{ $user->email }}</p>
                        </div>
                        <div>
                            <p class="text-text-subtle text-xs mb-0.5">Роль</p>
                            @if ($user->isAdmin())
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-brand-100 text-brand-700">Админ</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-muted text-text-muted">Пользователь</span>
                            @endif
                        </div>
                        <div>
                            <p class="text-text-subtle text-xs mb-0.5">Email подтверждён</p>
                            @if ($user->email_verified_at)
                                <span class="text-success text-xs">{{ $user->email_verified_at->format('d.m.Y') }}</span>
                            @else
                                <span class="text-text-muted text-xs">Не подтверждён</span>
                            @endif
                        </div>
                        <div>
                            <p class="text-text-subtle text-xs mb-0.5">Зарегистрирован</p>
                            <p class="text-text-primary">{{ $user->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-text-subtle text-xs mb-0.5">Статус</p>
                            @if ($user->isBanned())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-error-light text-error">
                                    <span class="w-1.5 h-1.5 rounded-full bg-error shrink-0"></span>
                                    Заблокирован с {{ $user->banned_at->format('d.m.Y') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-success-light text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success-dot shrink-0"></span>
                                    Активен
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap gap-2 mt-5 pt-5 border-t border-border-subtle">
                @if ($user->isBanned())
                    <form method="POST" action="{{ route('admin.users.unban', $user) }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-success hover:opacity-90 rounded-lg transition"
                        >
                            Разблокировать
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.users.ban', $user) }}">
                        @csrf
                        @method('PATCH')
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-error-muted hover:bg-error-hover rounded-lg transition"
                        >
                            Заблокировать
                        </button>
                    </form>
                @endif

                @if (!$user->email_verified_at)
                    <form method="POST" action="{{ route('admin.users.verify-email', $user) }}">
                        @csrf
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium text-text-primary border border-border-subtle rounded-lg hover:bg-surface-muted transition"
                        >
                            Подтвердить email
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('admin.users.send-password-reset', $user) }}">
                    @csrf
                    <button
                        type="submit"
                        class="px-4 py-2 text-sm font-medium text-text-primary border border-border-subtle rounded-lg hover:bg-surface-muted transition"
                    >
                        Сброс пароля
                    </button>
                </form>
            </div>
        </div>

        {{-- Owned books --}}
        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-border-subtle flex items-center justify-between">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">
                    Библиотека ({{ $user->userBooks->count() }})
                </h2>

                {{-- Grant book form --}}
                <div x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-brand-700 hover:bg-brand-900 text-white rounded-lg transition"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Выдать книгу
                    </button>

                    <div x-show="open" x-cloak class="mt-3 p-4 bg-surface-muted border border-border-subtle rounded-lg">
                        <form method="POST" action="{{ route('admin.users.grant-book', $user) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-text-primary mb-1">Книга <span class="text-error">*</span></label>
                                <select
                                    name="book_slug"
                                    required
                                    class="w-full px-3 py-2 rounded-lg border border-border-subtle text-sm text-text-primary bg-surface focus:outline-none focus:ring-2 focus:ring-brand-500"
                                >
                                    <option value="">— выберите книгу —</option>
                                    @foreach ($books as $book)
                                        <option value="{{ $book->slug }}">{{ $book->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-primary mb-1">Причина</label>
                                <input
                                    type="text"
                                    name="reason"
                                    maxlength="500"
                                    placeholder="Необязательно"
                                    class="w-full px-3 py-2 rounded-lg border border-border-subtle text-sm text-text-primary bg-surface focus:outline-none focus:ring-2 focus:ring-brand-500"
                                >
                            </div>
                            <div class="flex gap-2">
                                <button
                                    type="submit"
                                    class="px-4 py-1.5 text-xs font-medium text-white bg-brand-700 hover:bg-brand-900 rounded-lg transition"
                                >
                                    Выдать
                                </button>
                                <button
                                    type="button"
                                    @click="open = false"
                                    class="px-4 py-1.5 text-xs font-medium text-text-primary border border-border-subtle rounded-lg hover:bg-surface-muted transition"
                                >
                                    Отмена
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if ($user->userBooks->isEmpty())
                <div class="px-6 py-8 text-center">
                    <p class="text-sm text-text-muted">Книг в библиотеке нет</p>
                </div>
            @else
                <div class="divide-y divide-border-subtle">
                    @foreach ($user->userBooks as $userBook)
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                @if ($userBook->book?->cover_thumb_url)
                                    <img
                                        src="{{ $userBook->book->cover_thumb_url }}"
                                        alt="{{ $userBook->book->title }}"
                                        class="w-8 h-12 object-cover rounded shrink-0"
                                    >
                                @else
                                    <div class="w-8 h-12 bg-surface-muted border border-border-subtle rounded shrink-0"></div>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-text-primary truncate">
                                        {{ $userBook->book?->title ?? 'Книга удалена' }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        @if ($userBook->order_id)
                                            <a href="{{ route('admin.orders.show', $userBook->order_id) }}" class="text-xs text-brand-700 hover:underline">
                                                Заказ #{{ $userBook->order_id }}
                                            </a>
                                        @else
                                            <span class="text-xs text-text-subtle">Ручная выдача</span>
                                        @endif
                                        <span class="text-xs text-text-subtle">{{ $userBook->granted_at->format('d.m.Y') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                @if ($userBook->isRevoked())
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-error-light text-error">
                                        Отозван {{ $userBook->revoked_at->format('d.m.Y') }}
                                    </span>
                                    <form method="POST" action="{{ route('admin.user-books.restore', $userBook) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="text-xs text-brand-700 hover:text-brand-900 transition"
                                        >
                                            Восстановить
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-success-light text-success">
                                        Активна
                                    </span>
                                    <form method="POST" action="{{ route('admin.user-books.revoke', $userBook) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="text-xs text-error hover:text-error-hover transition"
                                        >
                                            Отозвать
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Orders --}}
        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-border-subtle">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">
                    Заказы ({{ $user->orders->count() }})
                </h2>
            </div>

            @if ($user->orders->isEmpty())
                <div class="px-6 py-8 text-center">
                    <p class="text-sm text-text-muted">Заказов нет</p>
                </div>
            @else
                <div class="divide-y divide-border-subtle">
                    @foreach ($user->orders as $order)
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-medium text-text-primary hover:text-brand-700 transition">
                                        Заказ #{{ $order->id }}
                                    </a>
                                    @switch($order->status->value)
                                        @case('paid')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-success-light text-success">
                                                <span class="w-1.5 h-1.5 rounded-full bg-success-dot shrink-0"></span>
                                                Оплачен
                                            </span>
                                            @break
                                        @case('pending')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-warning-light text-warning">
                                                Ожидание
                                            </span>
                                            @break
                                        @case('refunded')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-surface-muted text-text-muted">
                                                Возвращён
                                            </span>
                                            @break
                                        @case('failed')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-error-light text-error">
                                                Ошибка
                                            </span>
                                            @break
                                    @endswitch
                                </div>
                                <p class="text-xs text-text-subtle mt-0.5">{{ $order->created_at->format('d.m.Y H:i') }}</p>
                            </div>
                            <p class="text-sm font-medium text-text-primary shrink-0">
                                {{ number_format($order->total_amount / 100, 0, '.', ' ') }} ₽
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

</div>

@endsection
