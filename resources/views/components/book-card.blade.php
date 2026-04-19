@props(['book', 'ownedBookIds' => null])

@php
    $isOwned = $ownedBookIds && $ownedBookIds->contains($book->id);
@endphp

<div class="flex flex-col bg-white rounded-lg border border-border-subtle overflow-hidden shadow-sm hover:shadow-md transition-shadow">

    {{-- Cover --}}
    <a href="{{ route('books.show', $book) }}" class="relative block aspect-[2/3] overflow-hidden bg-surface-muted">
        @if($book->cover_thumb_url)
            <img
                src="{{ $book->cover_thumb_url }}"
                alt="{{ $book->title }}"
                class="w-full h-full object-cover"
                loading="lazy"
            >
        @else
            {{-- Placeholder with initials --}}
            <div class="w-full h-full flex items-center justify-center bg-brand-100">
                <span class="font-serif text-2xl text-brand-700 text-center px-3 leading-tight">
                    {{ mb_substr($book->title, 0, 2) }}
                </span>
            </div>
        @endif

        @if($book->isAdult())
            <span class="absolute top-2 right-2 px-1.5 py-0.5 text-xs font-sans font-bold leading-none text-white bg-error rounded">
                18+
            </span>
        @endif
    </a>

    {{-- Info --}}
    <div class="p-4 flex flex-col flex-1 gap-2">
        <a href="{{ route('books.show', $book) }}" class="block">
            <h3 class="font-serif text-base leading-snug text-text-primary hover:text-brand-700 transition line-clamp-3">
                {{ $book->title }}
            </h3>
        </a>

        <div class="mt-auto pt-3 flex items-center justify-between gap-2">
            <span class="font-sans font-semibold text-sm text-brand-700">
                {{ number_format($book->price / 100, config('shop.currency_decimals'), config('shop.currency_decimal_sep'), ' ') }}&nbsp;{{ config('shop.currency_symbol') }}
            </span>

            @unless(auth()->user()?->isAdmin())
                @if($isOwned)
                    {{-- State: already in library --}}
                    <a
                        href="{{ url('/cabinet/library') }}"
                        class="px-3 py-1.5 text-xs font-sans rounded border border-success-border text-success bg-success-light hover:bg-success-light transition"
                    >
                        В библиотеке
                    </a>
                @else
                    {{-- Default state: add to cart --}}
                    <form
                        method="POST"
                        action="{{ route('cart.store', $book) }}"
                        data-ga-id="{{ $book->id }}"
                        data-ga-name="{{ $book->title }}"
                        data-ga-price="{{ $book->price / 100 }}"
                        @submit="
                            if (typeof gtag !== 'undefined') {
                                gtag('event', 'add_to_cart', {
                                    currency: '{{ config('shop.currency_code') }}',
                                    value: Number($el.dataset.gaPrice),
                                    items: [{ item_id: $el.dataset.gaId, item_name: $el.dataset.gaName, price: Number($el.dataset.gaPrice) }]
                                });
                            }
                        "
                    >
                        @csrf
                        <button
                            type="submit"
                            class="px-3 py-1.5 text-xs font-sans rounded border border-brand-700 text-brand-700 hover:bg-brand-50 transition"
                            title="Добавить в корзину"
                        >
                            В корзину
                        </button>
                    </form>
                @endif
            @endunless
        </div>
    </div>

</div>
