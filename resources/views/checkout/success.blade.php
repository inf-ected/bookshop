@extends('layouts.app', ['title' => 'Оплата — ' . config('app.name')])

@if ($order && $order->status->value === 'paid')
@push('head')
<script>
    window.addEventListener('DOMContentLoaded', function () {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {
                transaction_id: '{{ $order->id }}',
                currency: 'RUB',
                value: {{ $order->total_amount / 100 }},
                items: [
                    @foreach ($order->items as $item)
                    { item_id: '{{ $item->book?->id }}', item_name: {{ Js::from($item->book?->title ?? '') }}, price: {{ $item->price / 100 }} },
                    @endforeach
                ]
            });
        }
    });
</script>
@endpush
@endif

@section('content')
<div class="max-w-lg mx-auto px-4 py-16 text-center">

    @if ($order && $order->status->value === 'paid')

        {{-- ── State: already paid on arrival ───────────────────────────── --}}
        <div class="w-20 h-20 rounded-full bg-success-light border-2 border-success-border flex items-center justify-center mx-auto mb-8">
            <svg class="w-10 h-10 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="font-serif text-3xl text-text-primary mb-3">Оплата прошла успешно!</h1>
        <p class="font-sans text-text-muted mb-8">Ваши книги добавлены в библиотеку.</p>
        <a
            href="{{ route('cabinet.index') }}"
            class="inline-block px-8 py-3 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition font-semibold"
        >
            Перейти в библиотеку
        </a>

    @elseif ($order)

        {{-- ── State: polling page ────────────────────────────────────────── --}}
        <div
            x-data="orderStatusPoller('{{ route('checkout.status', $order) }}')"
            x-init="startPolling()"
        >

            {{-- Pending --}}
            <div x-show="!paid && !timedOut">
                <div class="w-20 h-20 rounded-full bg-brand-50 border-2 border-brand-200 flex items-center justify-center mx-auto mb-8">
                    <svg
                        class="w-10 h-10 text-brand-600 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                </div>
                <h1 class="font-serif text-3xl text-text-primary mb-3">Обрабатываем оплату…</h1>
                <p class="font-sans text-text-muted">Обычно это занимает несколько секунд.</p>
            </div>

            {{-- Success (transition from pending) --}}
            <div x-show="paid" x-cloak>
                <div class="w-20 h-20 rounded-full bg-success-light border-2 border-success-border flex items-center justify-center mx-auto mb-8">
                    <svg class="w-10 h-10 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h1 class="font-serif text-3xl text-text-primary mb-3">Оплата прошла успешно!</h1>
                <p class="font-sans text-text-muted mb-8">Ваши книги добавлены в библиотеку.</p>
                <a
                    href="{{ route('cabinet.index') }}"
                    class="inline-block px-8 py-3 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition font-semibold"
                >
                    Перейти в библиотеку
                </a>
            </div>

            {{-- Timed out --}}
            <div x-show="timedOut && !paid" x-cloak>
                <div class="w-20 h-20 rounded-full bg-warning-light border-2 border-warning-border flex items-center justify-center mx-auto mb-8">
                    <svg class="w-10 h-10 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h1 class="font-serif text-3xl text-text-primary mb-3">Статус оплаты уточняется</h1>
                <p class="font-sans text-text-muted mb-8">
                    Проверьте библиотеку через несколько минут — книги появятся после подтверждения оплаты.
                </p>
                <a
                    href="{{ route('cabinet.index') }}"
                    class="inline-block px-8 py-3 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition font-semibold"
                >
                    Перейти в библиотеку
                </a>
            </div>

        </div>

    @else

        {{-- ── State: no order found ──────────────────────────────────────── --}}
        <div class="w-20 h-20 rounded-full bg-brand-50 border-2 border-brand-200 flex items-center justify-center mx-auto mb-8">
            <svg class="w-10 h-10 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
        </div>
        <h1 class="font-serif text-3xl text-text-primary mb-3">Спасибо за покупку!</h1>
        <p class="font-sans text-text-muted mb-8">Ваши книги будут доступны в библиотеке после подтверждения оплаты.</p>
        <a
            href="{{ route('cabinet.index') }}"
            class="inline-block px-8 py-3 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition font-semibold"
        >
            Перейти в библиотеку
        </a>

    @endif

</div>

<script>
    function orderStatusPoller(statusUrl) {
        return {
            paid: false,
            timedOut: false,
            attempts: 0,
            maxAttempts: 15,

            startPolling() {
                const interval = setInterval(async () => {
                    this.attempts++;

                    try {
                        const response = await fetch(statusUrl, {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await response.json();

                        if (data.paid) {
                            this.paid = true;
                            clearInterval(interval);
                            setTimeout(() => {
                                window.location.href = '{{ route('cabinet.index') }}';
                            }, 2000);
                            return;
                        }
                    } catch (e) {
                        // Network error — keep polling
                    }

                    if (this.attempts >= this.maxAttempts) {
                        this.timedOut = true;
                        clearInterval(interval);
                    }
                }, 2000);
            },
        };
    }
</script>
@endsection
