@extends('layouts.app')

@section('content')

<div class="max-w-2xl mx-auto px-4 py-10">

    {{-- Breadcrumb --}}
    <nav class="text-xs text-text-subtle mb-6 font-sans">
        <a href="{{ route('books.index') }}" class="hover:text-text-muted transition">Каталог</a>
        <span class="mx-2">/</span>
        <a href="{{ route('books.show', $book) }}" class="hover:text-text-muted transition">{{ $book->title }}</a>
        <span class="mx-2">/</span>
        <span>Фрагмент</span>
    </nav>

    <div class="mb-8">
        <p class="text-xs font-sans font-semibold uppercase tracking-widest text-text-subtle mb-1">
            Ознакомительный фрагмент
        </p>
        <h1 class="font-serif text-2xl md:text-3xl text-text-primary">{{ $book->title }}</h1>
    </div>

    {{-- Alpine pagination component --}}
    <div
        x-data="fragmentPager({{ json_encode($book->fragment ?? '') }}, 3000)"
        x-init="init()"
    >

        {{-- Page info --}}
        <div class="flex items-center justify-between mb-6 text-xs font-sans text-text-muted">
            <span>Страница <span x-text="currentPage + 1"></span> из <span x-text="totalPages"></span></span>
            <span class="text-text-subtle">Чтение фрагмента</span>
        </div>

        {{-- Fragment text — copy protected --}}
        <div
            class="no-select font-serif text-base leading-relaxed text-text-primary"
            style="pointer-events: none;"
            @contextmenu.prevent
            @keydown.ctrl.c.window.prevent
            @keydown.meta.c.window.prevent
        >
            <div x-html="currentPageText"></div>

            {{-- End cap on last page --}}
            <template x-if="currentPage === totalPages - 1">
                <div class="mt-10 pt-8 border-t border-border-subtle text-center">
                    <p class="font-serif text-lg text-text-muted mb-6">— Конец ознакомительного фрагмента —</p>
                </div>
            </template>
        </div>

        {{-- CTA block (outside no-select so it's clickable) --}}
        <template x-if="currentPage === totalPages - 1">
            <div class="mt-6 text-center" style="pointer-events: auto;">
                <a
                    href="{{ route('books.show', $book) }}"
                    class="inline-block px-8 py-3 bg-accent text-white font-sans text-sm rounded hover:bg-accent-dark transition"
                >
                    Купить полную книгу
                </a>
                <p class="mt-2 text-xs text-text-subtle">
                    {{ number_format($book->price / 100, 0, ',', ' ') }}&nbsp;₽
                </p>
            </div>
        </template>

        {{-- Pagination controls --}}
        <div class="flex items-center justify-between mt-10 pt-6 border-t border-border-subtle" style="pointer-events: auto;">

            <button
                type="button"
                @click="prevPage()"
                :disabled="currentPage === 0"
                class="px-5 py-2 border border-border-subtle rounded font-sans text-sm transition"
                :class="currentPage === 0
                    ? 'text-text-subtle cursor-not-allowed opacity-40'
                    : 'text-text-primary hover:bg-surface-muted'"
            >
                &larr; Назад
            </button>

            <span class="text-xs text-text-subtle font-sans">
                <span x-text="currentPage + 1"></span>&nbsp;/&nbsp;<span x-text="totalPages"></span>
            </span>

            <button
                type="button"
                @click="nextPage()"
                :disabled="currentPage === totalPages - 1"
                class="px-5 py-2 border border-border-subtle rounded font-sans text-sm transition"
                :class="currentPage === totalPages - 1
                    ? 'text-text-subtle cursor-not-allowed opacity-40'
                    : 'text-brand-700 border-brand-300 hover:bg-brand-50'"
            >
                Вперёд &rarr;
            </button>

        </div>

    </div>

</div>

@push('head')
<script>
    function fragmentPager(text, pageSize) {
        return {
            text: text || '',
            pageSize: pageSize,
            pages: [],
            currentPage: 0,
            totalPages: 1,
            currentPageText: '',

            init() {
                this.pages = this.splitIntoPages(this.text, this.pageSize);
                this.totalPages = this.pages.length;
                this.currentPageText = this.pages[0] || '';
            },

            escapeHtml(text) {
                const div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            },

            splitIntoPages(text, size) {
                if (!text) return [''];
                const pages = [];
                let i = 0;
                while (i < text.length) {
                    let end = i + size;
                    if (end < text.length) {
                        // Try to break at a paragraph boundary first
                        const paraBreak = text.lastIndexOf('\n\n', end);
                        if (paraBreak > i) {
                            end = paraBreak;
                        } else {
                            // Fall back to word boundary
                            const wordBreak = text.lastIndexOf(' ', end);
                            if (wordBreak > i) {
                                end = wordBreak;
                            }
                        }
                    }
                    const chunk = this.escapeHtml(text.slice(i, end).trim());
                    // Convert newlines to <br> tags for display
                    pages.push(chunk.replace(/\n\n+/g, '</p><p class="mt-4">').replace(/\n/g, '<br>'));
                    i = end;
                }
                return pages.map(p => '<p class="mt-4 first:mt-0">' + p + '</p>');
            },

            nextPage() {
                if (this.currentPage < this.totalPages - 1) {
                    this.currentPage++;
                    this.currentPageText = this.pages[this.currentPage];
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            prevPage() {
                if (this.currentPage > 0) {
                    this.currentPage--;
                    this.currentPageText = this.pages[this.currentPage];
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }
        };
    }
</script>
@endpush

@endsection
