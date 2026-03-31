@extends('layouts.app', ['title' => 'Оплата — ' . config('app.name')])

@section('content')
<div class="max-w-2xl mx-auto px-4 py-16 text-center">

    @if ($order && $order->status->value === 'paid')
        {{-- Already paid — should have been redirected, but handle anyway --}}
        <div class="text-success text-6xl mb-6">✓</div>
        <h1 class="text-3xl font-bold mb-4">Оплата прошла успешно!</h1>
        <p class="text-text-muted mb-8">Книги добавлены в вашу библиотеку.</p>
        <a href="{{ url('/cabinet/library') }}" class="btn-primary">Перейти в библиотеку</a>

    @elseif ($order)
        {{-- Pending — show polling page --}}
        <div
            x-data="orderStatusPoller('{{ route('checkout.status', $order) }}')"
            x-init="startPolling()"
        >
            <div x-show="!paid && !timedOut">
                <div class="text-4xl mb-6 animate-spin inline-block">⏳</div>
                <h1 class="text-3xl font-bold mb-4">Обрабатываем оплату…</h1>
                <p class="text-text-muted">Это займёт несколько секунд.</p>
            </div>

            <div x-show="paid" x-cloak>
                <div class="text-success text-6xl mb-6">✓</div>
                <h1 class="text-3xl font-bold mb-4">Оплата прошла успешно!</h1>
                <p class="text-text-muted mb-8">Книги добавлены в вашу библиотеку.</p>
                <a href="{{ url('/cabinet/library') }}" class="btn-primary">Перейти в библиотеку</a>
            </div>

            <div x-show="timedOut && !paid" x-cloak>
                <div class="text-warning text-6xl mb-6">⏱</div>
                <h1 class="text-3xl font-bold mb-4">Статус оплаты уточняется</h1>
                <p class="text-text-muted mb-8">Проверьте библиотеку через несколько минут — книги появятся после подтверждения оплаты.</p>
                <a href="{{ url('/cabinet/library') }}" class="btn-primary">Перейти в библиотеку</a>
            </div>
        </div>

    @else
        {{-- No order found --}}
        <div class="text-6xl mb-6">📚</div>
        <h1 class="text-3xl font-bold mb-4">Спасибо за покупку!</h1>
        <p class="text-text-muted mb-8">Ваши книги будут доступны в библиотеке после подтверждения оплаты.</p>
        <a href="{{ url('/cabinet/library') }}" class="btn-primary">Перейти в библиотеку</a>
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
