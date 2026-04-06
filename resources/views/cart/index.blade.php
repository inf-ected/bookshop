@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="mb-8 pb-6 border-b border-border-subtle">
        <h1 class="font-serif text-3xl text-text-primary">Корзина</h1>
    </div>

    @if($items->isEmpty())

        {{-- ── State 1: Empty cart ──────────────────────────────────────────── --}}
        <div class="bg-white border border-border-subtle rounded-xl p-12 text-center">
            <div class="mx-auto w-16 h-16 bg-surface-muted rounded-full flex items-center justify-center mb-5">
                <svg class="w-8 h-8 text-text-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3A1 1 0 006 17h12M9 21a1 1 0 100-2 1 1 0 000 2zm10 0a1 1 0 100-2 1 1 0 000 2z"/>
                </svg>
            </div>
            <p class="font-serif text-xl text-text-primary mb-2">Корзина пуста</p>
            <p class="text-sm text-text-muted mb-7">Добавьте книги из каталога, чтобы оформить заказ.</p>
            <a href="{{ route('books.index') }}"
               class="inline-block px-6 py-2.5 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition">
                Перейти в каталог
            </a>
        </div>

    @else

        {{-- ── States 2–4: Cart has items ──────────────────────────────────── --}}
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Items list --}}
            <div class="flex-1 space-y-4">
                @foreach($items as $item)
                    <div class="bg-white border border-border-subtle rounded-xl p-4 flex gap-4">

                        {{-- Cover --}}
                        <a href="{{ route('books.show', $item->book) }}" class="shrink-0">
                            @if($item->book->cover_thumb_url)
                                <img
                                    src="{{ $item->book->cover_thumb_url }}"
                                    alt="{{ $item->book->title }}"
                                    class="w-16 h-24 object-cover rounded shadow-sm"
                                    loading="lazy"
                                >
                            @else
                                <div class="w-16 h-24 bg-brand-100 rounded flex items-center justify-center shadow-sm">
                                    <span class="font-serif text-lg text-brand-700">
                                        {{ mb_substr($item->book->title, 0, 2) }}
                                    </span>
                                </div>
                            @endif
                        </a>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0 flex flex-col gap-1">
                            <a href="{{ route('books.show', $item->book) }}"
                               class="font-serif text-base leading-snug text-text-primary hover:text-brand-700 transition line-clamp-2">
                                {{ $item->book->title }}
                            </a>
                            <p class="mt-auto font-sans font-semibold text-sm text-brand-700">
                                {{ number_format($item->book->price / 100, 0, ',', ' ') }}&nbsp;₽
                            </p>
                        </div>

                        {{-- Remove button --}}
                        <div class="shrink-0 flex items-start">
                            <form method="POST" action="{{ route('cart.destroy', $item->book) }}">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="p-1.5 text-text-subtle hover:text-error transition rounded hover:bg-error-light"
                                    title="Удалить из корзины"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </form>
                        </div>

                    </div>
                @endforeach
            </div>

            {{-- Summary sidebar --}}
            <div class="lg:w-72 shrink-0">
                <div class="bg-white border border-border-subtle rounded-xl p-6 sticky top-6">

                    <h2 class="font-serif text-lg text-text-primary mb-5">Итого</h2>

                    {{-- Items summary --}}
                    <div class="space-y-2 mb-5">
                        @foreach($items as $item)
                            <div class="flex justify-between gap-2 text-sm text-text-muted">
                                <span class="truncate">{{ $item->book->title }}</span>
                                <span class="shrink-0">{{ number_format($item->book->price / 100, 0, ',', ' ') }}&nbsp;₽</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Total --}}
                    <div class="border-t border-border-subtle pt-4 mb-6 flex justify-between items-baseline">
                        <span class="font-sans text-sm font-semibold text-text-primary">К оплате</span>
                        <span class="font-serif text-xl font-semibold text-text-primary">
                            {{ number_format($total / 100, 0, ',', ' ') }}&nbsp;₽
                        </span>
                    </div>

                    {{-- ── CTA block — state-dependent ────────────────────── --}}

                    @guest
                        {{-- State 2: guest --}}
                        <p class="text-sm text-text-muted mb-4 text-center">
                            Для оформления заказа необходимо войти в аккаунт.
                        </p>
                        <a href="{{ route('login') }}"
                           class="block w-full text-center px-5 py-2.5 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition mb-2">
                            Войти
                        </a>
                        <a href="{{ route('register') }}"
                           class="block w-full text-center px-5 py-2.5 border border-brand-700 text-brand-700 font-sans text-sm rounded hover:bg-brand-50 transition">
                            Зарегистрироваться
                        </a>
                    @endguest

                    @auth
                        @if(!auth()->user()->hasVerifiedEmail())
                            {{-- State 3: authenticated but email not verified --}}
                            <div class="bg-warning-light border border-warning-border rounded-lg p-4 text-sm text-warning">
                                <p class="font-semibold mb-1">Подтвердите email</p>
                                <p>Для оформления заказа необходимо подтвердить адрес электронной почты.</p>
                                <a href="{{ route('verification.notice') }}"
                                   class="inline-block mt-3 text-brand-700 hover:text-brand-900 font-semibold transition">
                                    Подтвердить email &rarr;
                                </a>
                            </div>
                        @else
                            {{-- State 4: authenticated + verified --}}
                            @if(\Illuminate\Support\Facades\Route::has('checkout.store'))
                                <form
                                    method="POST"
                                    action="{{ route('checkout.store') }}"
                                    data-ga-value="{{ $total / 100 }}"
                                    data-ga-items="{{ Js::from($items->map(fn($i) => ['item_id' => (string) $i->book->id, 'item_name' => $i->book->title, 'price' => $i->book->price / 100])->values()) }}"
                                    @submit="
                                        if (typeof gtag !== 'undefined') {
                                            gtag('event', 'begin_checkout', {
                                                currency: 'RUB',
                                                value: Number($el.dataset.gaValue),
                                                items: JSON.parse($el.dataset.gaItems)
                                            });
                                        }
                                    "
                                >
                                    @csrf
                                    <button
                                        type="submit"
                                        class="w-full px-5 py-2.5 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition font-semibold"
                                    >
                                        Оформить заказ
                                    </button>
                                </form>
                            @else
                                <button
                                    type="button"
                                    disabled
                                    class="w-full px-5 py-2.5 bg-brand-700 text-white font-sans text-sm rounded opacity-50 cursor-not-allowed font-semibold"
                                >
                                    Оформить заказ
                                </button>
                            @endif
                        @endif
                    @endauth

                </div>
            </div>

        </div>

    @endif

</div>

@endsection
