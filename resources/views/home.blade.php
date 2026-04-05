@extends('layouts.app')

<x-seo
    title="Книжная лавка"
    description="Качественная литература в цифровом формате — читайте где угодно, когда угодно. Мгновенный доступ после оплаты."
    og-type="website"
/>

@section('content')

{{-- Hero banner --}}
<section class="bg-brand-950 text-white py-14 px-4">
    <div class="max-w-5xl mx-auto text-center">
        <h1 class="font-serif text-3xl md:text-5xl leading-tight mb-4">
            Книги, которые стоит прочитать
        </h1>
        <p class="font-sans text-brand-200 text-base md:text-lg mb-8 max-w-xl mx-auto">
            Качественная литература в цифровом формате — читайте где угодно, когда угодно.
        </p>
        <a href="{{ route('books.index') }}" class="inline-block px-8 py-3 bg-accent text-white font-sans text-sm rounded hover:bg-accent-dark transition">
            Перейти в каталог
        </a>
    </div>
</section>

{{-- Featured books carousel --}}
<section class="py-12 px-4">
    <div class="max-w-5xl mx-auto">

        <div class="flex items-center justify-between mb-6">
            <h2 class="font-serif text-2xl text-text-primary">Книги</h2>
            <a href="{{ route('books.index') }}" class="text-sm font-sans text-brand-700 hover:text-brand-900 transition">
                Все книги &rarr;
            </a>
        </div>

        @if($books->isEmpty())
            <div class="py-16 text-center">
                <p class="font-serif text-xl text-text-muted">Скоро здесь появятся книги</p>
                <p class="text-sm text-text-subtle mt-2">Следите за обновлениями</p>
            </div>
        @else
            {{-- Carousel wrapper --}}
            <div
                x-data="{
                    active: 0,
                    total: {{ $books->count() }},
                    updateActive(el) {
                        const items = el.querySelectorAll('[data-slide]');
                        const scrollLeft = el.scrollLeft;
                        const itemWidth = items[0]?.offsetWidth + 16;
                        this.active = Math.round(scrollLeft / itemWidth);
                    }
                }"
            >
                <div
                    class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-4 -mx-4 px-4 md:mx-0 md:px-0"
                    style="scrollbar-width: none; -ms-overflow-style: none;"
                    @scroll.passive="updateActive($el)"
                >
                    @foreach($books as $i => $book)
                        <div class="snap-start shrink-0 w-44 md:w-52" data-slide="{{ $i }}">
                            <x-book-card :book="$book" :owned-book-ids="$ownedBookIds" />
                        </div>
                    @endforeach
                </div>

                {{-- Dot indicators --}}
                @if($books->count() > 1)
                    <div class="flex gap-2 justify-center mt-4">
                        @foreach($books as $i => $book)
                            <button
                                type="button"
                                class="w-2 h-2 rounded-full transition"
                                :class="active === {{ $i }} ? 'bg-brand-700' : 'bg-border-subtle'"
                                aria-label="Слайд {{ $i + 1 }}"
                            ></button>
                        @endforeach
                    </div>
                @endif

            </div>
        @endif

    </div>
</section>

{{-- Static promo strip --}}
<section class="bg-surface-muted border-t border-border-subtle py-10 px-4">
    <div class="max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6 text-center">

        <div class="space-y-2">
            <div class="text-2xl font-serif text-brand-700">📖</div>
            <h3 class="font-serif text-base">Мгновенный доступ</h3>
            <p class="text-sm text-text-muted">После оплаты книга сразу доступна в вашем личном кабинете.</p>
        </div>

        <div class="space-y-2">
            <div class="text-2xl font-serif text-brand-700">🔒</div>
            <h3 class="font-serif text-base">Безопасная оплата</h3>
            <p class="text-sm text-text-muted">Платёжи обрабатываются через защищённые каналы.</p>
        </div>

        <div class="space-y-2">
            <div class="text-2xl font-serif text-brand-700">♾️</div>
            <h3 class="font-serif text-base">Навсегда ваша</h3>
            <p class="text-sm text-text-muted">Купленные книги остаются в вашей библиотеке бессрочно.</p>
        </div>

    </div>
</section>

@endsection
