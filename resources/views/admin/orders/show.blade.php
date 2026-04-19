@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="mb-8">
        <h1 class="font-serif text-2xl text-text-primary">Заказ #{{ $order->id }}</h1>
        <p class="text-sm text-text-muted mt-1">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700 transition">Панель управления</a>
            &rsaquo;
            <a href="{{ route('admin.orders.index') }}" class="hover:text-brand-700 transition">Заказы</a>
            &rsaquo; #{{ $order->id }}
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

        {{-- Status + meta --}}
        <div class="bg-surface border border-border-subtle rounded-xl p-6 space-y-4">
            <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Информация о заказе</h2>

            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <p class="text-text-subtle text-xs mb-0.5">Статус</p>
                    @switch($order->status->value)
                        @case('paid')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-success-light text-success">
                                <span class="w-1.5 h-1.5 rounded-full bg-success-dot shrink-0"></span>
                                Оплачен
                            </span>
                            @break
                        @case('pending')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-warning-light text-warning">
                                <span class="w-1.5 h-1.5 rounded-full bg-warning shrink-0"></span>
                                Ожидание
                            </span>
                            @break
                        @case('refunded')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-surface-muted text-text-muted">
                                <span class="w-1.5 h-1.5 rounded-full bg-text-subtle shrink-0"></span>
                                Возвращён
                            </span>
                            @break
                        @case('failed')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-error-light text-error">
                                <span class="w-1.5 h-1.5 rounded-full bg-error shrink-0"></span>
                                Ошибка
                            </span>
                            @break
                        @default
                            <span class="text-xs text-text-muted">{{ $order->status->value }}</span>
                    @endswitch
                </div>

                <div>
                    <p class="text-text-subtle text-xs mb-0.5">Сумма</p>
                    <p class="font-medium text-text-primary">{{ number_format($order->total_amount / 100, 0, '.', ' ') }} {{ config('shop.currency_symbol') }}</p>
                </div>

                <div>
                    <p class="text-text-subtle text-xs mb-0.5">Создан</p>
                    <p class="text-text-primary">{{ $order->created_at->format('d.m.Y H:i') }}</p>
                </div>

                <div>
                    <p class="text-text-subtle text-xs mb-0.5">Оплачен</p>
                    <p class="text-text-primary">{{ $order->paid_at ? $order->paid_at->format('d.m.Y H:i') : '—' }}</p>
                </div>

                @if ($order->transaction?->provider_data['session_id'] ?? null)
                    <div class="col-span-2">
                        <p class="text-text-subtle text-xs mb-0.5">Session ID</p>
                        <p class="font-mono text-xs text-text-muted break-all">{{ $order->transaction->provider_data['session_id'] }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Customer info --}}
        @if ($order->user)
            <div class="bg-surface border border-border-subtle rounded-xl p-6 space-y-3">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Покупатель</h2>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-text-primary text-sm">{{ $order->user->name }}</p>
                        <p class="text-xs text-text-muted mt-0.5">{{ $order->user->email }}</p>
                    </div>
                    <a
                        href="{{ route('admin.users.show', $order->user) }}"
                        class="text-xs text-brand-700 hover:text-brand-900 transition"
                    >
                        Профиль пользователя &rarr;
                    </a>
                </div>
            </div>
        @endif

        {{-- Order items --}}
        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-border-subtle">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Состав заказа</h2>
            </div>
            <div class="divide-y divide-border-subtle">
                @foreach ($order->items as $item)
                    <div class="px-6 py-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            @if ($item->book?->cover_thumb_url)
                                <img
                                    src="{{ $item->book->cover_thumb_url }}"
                                    alt="{{ $item->book->title }}"
                                    class="w-8 h-12 object-cover rounded shrink-0"
                                >
                            @else
                                <div class="w-8 h-12 bg-surface-muted border border-border-subtle rounded flex items-center justify-center shrink-0">
                                    <svg class="w-3.5 h-3.5 text-text-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                </div>
                            @endif
                            <div>
                                @if ($item->book)
                                    <a href="{{ route('admin.books.edit', $item->book) }}" class="text-sm font-medium text-text-primary hover:text-brand-700 transition">
                                        {{ $item->book->title }}
                                    </a>
                                @else
                                    <p class="text-sm text-text-muted">Книга удалена</p>
                                @endif
                            </div>
                        </div>
                        <p class="text-sm font-medium text-text-primary shrink-0">
                            {{ number_format($item->price / 100, config('shop.currency_decimals'), config('shop.currency_decimal_sep'), ' ') }} {{ config('shop.currency_symbol') }}
                        </p>
                    </div>
                @endforeach
            </div>
            <div class="px-6 py-4 bg-surface-muted border-t border-border-subtle flex justify-between items-center">
                <span class="text-sm font-semibold text-text-primary">Итого</span>
                <span class="font-serif text-lg font-semibold text-text-primary">
                    {{ number_format($order->total_amount / 100, 0, '.', ' ') }} {{ config('shop.currency_symbol') }}
                </span>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3">
            <a
                href="{{ route('admin.orders.index') }}"
                class="px-4 py-2 text-sm font-medium text-text-primary border border-border-subtle rounded-lg hover:bg-surface-muted transition"
            >
                &larr; К заказам
            </a>

            @if ($order->status->value === 'paid')
                <div x-data="{ open: false }">
                    <button
                        @click="open = true"
                        class="px-4 py-2 text-sm font-medium text-white bg-error-muted hover:bg-error-hover rounded-lg transition"
                    >
                        Сделать возврат
                    </button>

                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 z-50 flex items-center justify-center px-4"
                        style="display: none;"
                    >
                        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
                        <div class="relative bg-surface rounded-xl shadow-xl p-6 max-w-sm w-full z-10">
                            <h3 class="font-serif text-lg text-text-primary mb-2">Подтвердить возврат?</h3>
                            <p class="text-sm text-text-muted mb-6">
                                Заказ #{{ $order->id }} будет помечен как возвращённый. Убедитесь, что возврат уже выполнен в Stripe.
                            </p>
                            <div class="flex gap-3 justify-end">
                                <button
                                    @click="open = false"
                                    class="px-4 py-2 text-sm font-medium text-text-primary border border-border-subtle rounded-lg hover:bg-surface-muted transition"
                                >
                                    Отмена
                                </button>
                                <form method="POST" action="{{ route('admin.orders.refund', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        class="px-4 py-2 text-sm font-medium text-white bg-error-muted hover:bg-error-hover rounded-lg transition"
                                    >
                                        Подтвердить
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

    </div>

</div>

@endsection
