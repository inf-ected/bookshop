@props(['book'])

<div class="flex flex-col bg-white rounded-lg border border-border-subtle overflow-hidden shadow-sm hover:shadow-md transition-shadow">

    {{-- Cover --}}
    <a href="{{ route('books.show', $book) }}" class="block aspect-[2/3] overflow-hidden bg-surface-muted">
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
    </a>

    {{-- Info --}}
    <div class="p-4 flex flex-col flex-1 gap-2">
        <a href="{{ route('books.show', $book) }}" class="block">
            <h3 class="font-serif text-base leading-snug text-text-primary hover:text-brand-700 transition line-clamp-3">
                {{ $book->title }}
            </h3>
        </a>

        <div class="mt-auto pt-3 flex items-center justify-between gap-2">
            <span class="text-accent font-sans font-semibold text-sm">
                {{ number_format($book->price / 100, 0, ',', ' ') }}&nbsp;₽
            </span>

            {{-- Cart button — disabled until Phase 5 --}}
            <button
                type="button"
                disabled
                class="px-3 py-1.5 text-xs font-sans rounded border border-border-subtle text-text-subtle cursor-not-allowed bg-surface-muted"
                title="Добавить в корзину"
            >
                В корзину
            </button>
        </div>
    </div>

</div>
