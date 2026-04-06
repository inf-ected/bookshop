@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="font-serif text-2xl text-text-primary">Заказы</h1>
            <p class="text-sm text-text-muted mt-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700 transition">Панель управления</a>
                &rsaquo; Заказы
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

    {{-- Status filter --}}
    <div class="mb-5 flex flex-wrap gap-2">
        <a
            href="{{ route('admin.orders.index') }}"
            class="px-3 py-1.5 text-xs font-medium rounded-full border transition
                {{ $status === '' ? 'bg-brand-700 text-white border-brand-700' : 'bg-surface text-text-muted border-border-subtle hover:border-brand-300 hover:text-brand-700' }}"
        >
            Все
        </a>
        @foreach ($statuses as $s)
            <a
                href="{{ route('admin.orders.index', ['status' => $s->value]) }}"
                class="px-3 py-1.5 text-xs font-medium rounded-full border transition
                    {{ $status === $s->value ? 'bg-brand-700 text-white border-brand-700' : 'bg-surface text-text-muted border-border-subtle hover:border-brand-300 hover:text-brand-700' }}"
            >
                @switch($s->value)
                    @case('pending') Ожидание @break
                    @case('paid') Оплачен @break
                    @case('refunded') Возвращён @break
                    @case('failed') Ошибка @break
                    @default {{ $s->value }}
                @endswitch
            </a>
        @endforeach
    </div>

    @if ($orders->isEmpty())

        <div class="bg-surface border border-border-subtle rounded-xl py-16 text-center">
            <svg class="w-12 h-12 text-text-subtle mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="font-serif text-lg text-text-muted">Заказов нет</p>
        </div>

    @else

        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm font-sans">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-muted">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-16">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider">Покупатель</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-28">Сумма</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-32">Статус</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-40">Оплачен</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-40">Создан</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-text-muted uppercase tracking-wider w-20">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-surface-muted transition">

                                <td class="px-4 py-3 text-text-muted font-mono text-xs">
                                    #{{ $order->id }}
                                </td>

                                <td class="px-4 py-3">
                                    @if ($order->user)
                                        <a href="{{ route('admin.users.show', $order->user) }}" class="text-text-primary hover:text-brand-700 transition font-medium">
                                            {{ $order->user->name }}
                                        </a>
                                        <p class="text-xs text-text-subtle mt-0.5">{{ $order->user->email }}</p>
                                    @else
                                        <span class="text-text-subtle">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-text-primary font-medium">
                                    {{ number_format($order->total_amount / 100, 0, '.', ' ') }} ₽
                                </td>

                                {{-- Status badge --}}
                                <td class="px-4 py-3">
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
                                </td>

                                <td class="px-4 py-3 text-text-muted text-xs">
                                    {{ $order->paid_at ? $order->paid_at->format('d.m.Y H:i') : '—' }}
                                </td>

                                <td class="px-4 py-3 text-text-muted text-xs">
                                    {{ $order->created_at->format('d.m.Y H:i') }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <a
                                        href="{{ route('admin.orders.show', $order) }}"
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

        @if ($orders->hasPages())
            <div class="mt-6">
                {{ $orders->links() }}
            </div>
        @endif

    @endif

</div>

@endsection
