@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="font-serif text-3xl text-text-primary">История заказов</h1>
    </div>

    {{-- Cabinet tab navigation --}}
    <x-cabinet-nav />

    @if($orders->isEmpty())

        {{-- Empty state --}}
        <div class="bg-white border border-border-subtle rounded-xl p-12 text-center">
            <div class="mx-auto w-16 h-16 bg-surface-muted rounded-full flex items-center justify-center mb-5">
                <svg class="w-8 h-8 text-text-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <p class="font-serif text-xl text-text-primary mb-2">Заказов пока нет</p>
            <p class="text-sm text-text-muted mb-7">Оформите заказ, и он появится здесь.</p>
            <a href="{{ route('books.index') }}"
               class="inline-block px-6 py-2.5 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition">
                Перейти в каталог
            </a>
        </div>

    @else

        {{-- Orders list --}}
        <div class="space-y-4">
            @foreach($orders as $order)

                @php
                    $statusLabel = match($order->status) {
                        \App\Enums\OrderStatus::Pending   => 'Ожидает оплаты',
                        \App\Enums\OrderStatus::Paid      => 'Оплачен',
                        \App\Enums\OrderStatus::Refunded  => 'Возврат',
                        \App\Enums\OrderStatus::Failed    => 'Ошибка',
                    };
                    $statusClass = match($order->status) {
                        \App\Enums\OrderStatus::Pending  => 'bg-warning-light text-warning border-warning-border',
                        \App\Enums\OrderStatus::Paid     => 'bg-success-light text-success border-success-border',
                        \App\Enums\OrderStatus::Refunded => 'bg-surface-muted text-text-muted border-border-subtle',
                        \App\Enums\OrderStatus::Failed   => 'bg-error-light text-error border-error-border',
                    };
                @endphp

                <div class="bg-white border border-border-subtle rounded-xl overflow-hidden">

                    {{-- Order header --}}
                    <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-3 border-b border-border-subtle bg-surface-muted">
                        <div class="flex items-center gap-4">
                            <span class="font-sans font-semibold text-text-primary text-sm">#{{ $order->id }}</span>
                            <span class="text-text-subtle text-xs font-sans">
                                {{ $order->created_at->translatedFormat('d F Y') }}
                            </span>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-sans font-medium border {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                    </div>

                    {{-- Order items --}}
                    <div class="px-5 py-4">
                        <ul class="space-y-2">
                            @foreach($order->items as $item)
                                <li class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-text-primary font-sans leading-snug line-clamp-1">
                                        {{ $item->book->title }}
                                    </span>
                                    <span class="shrink-0 text-sm font-sans text-text-muted">
                                        {{ number_format($item->price / 100, config('shop.currency_decimals'), config('shop.currency_decimal_sep'), ' ') }}&nbsp;{{ config('shop.currency_symbol') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        {{-- Total --}}
                        <div class="mt-4 pt-4 border-t border-border-subtle flex justify-between items-baseline">
                            <span class="text-xs font-sans text-text-muted">Итого</span>
                            <span class="font-serif text-lg font-semibold text-text-primary">
                                {{ number_format($order->total_amount / 100, 0, ',', ' ') }}&nbsp;{{ config('shop.currency_symbol') }}
                            </span>
                        </div>
                    </div>

                </div>

            @endforeach
        </div>

        {{-- Pagination --}}
        @if($orders->hasPages())
            <div class="mt-8">
                {{ $orders->links() }}
            </div>
        @endif

    @endif

</div>

@endsection
