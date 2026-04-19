@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="font-serif text-2xl text-text-primary">Книги</h1>
            <p class="text-sm text-text-muted mt-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700 transition">Панель управления</a>
                &rsaquo; Книги
            </p>
        </div>
        <a
            href="{{ route('admin.books.create') }}"
            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-700 hover:bg-brand-900 text-white text-sm font-sans font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Добавить книгу
        </a>
    </div>

    {{-- Flash success --}}
    @if (session('success'))
        <div class="mb-5 px-4 py-3 bg-success-light border border-success-border rounded-lg text-sm text-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Flash error --}}
    @if (session('error'))
        <div class="mb-5 px-4 py-3 bg-error-light border border-error-border rounded-lg text-sm text-error">
            {{ session('error') }}
        </div>
    @endif

    {{-- Validation errors (e.g. toggle-status rule 17) --}}
    @if ($errors->any())
        <div class="mb-5 px-4 py-3 bg-error-light border border-error-border rounded-lg text-sm text-error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if ($books->isEmpty())
        {{-- Empty state --}}
        <div class="bg-surface border border-border-subtle rounded-xl py-16 text-center">
            <svg class="w-12 h-12 text-text-subtle mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <p class="font-serif text-lg text-text-muted mb-2">Книг пока нет</p>
            <p class="text-sm text-text-subtle mb-6">Добавьте первую книгу в каталог</p>
            <a
                href="{{ route('admin.books.create') }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-brand-700 hover:bg-brand-900 text-white text-sm font-sans font-medium rounded-lg transition"
            >
                Добавить книгу
            </a>
        </div>

    @else

        {{-- Table --}}
        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm font-sans">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-muted">
                            <th class="px-4 py-3 text-center text-xs font-semibold text-text-muted uppercase tracking-wider w-12">
                                Поз.
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-14">
                                Обложка
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider">
                                Название
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-28">
                                Цена
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-32">
                                Статус
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-text-muted uppercase tracking-wider w-28">
                                Избранное
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-32">
                                В продаже
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-text-muted uppercase tracking-wider w-28">
                                Действия
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach ($books as $book)
                            <tr class="hover:bg-surface-muted transition">

                                {{-- Sort order --}}
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block min-w-[1.5rem] px-1.5 py-0.5 text-xs font-mono font-medium text-text-muted bg-surface-muted border border-border-subtle rounded">
                                        {{ $book->sort_order }}
                                    </span>
                                </td>

                                {{-- Cover thumbnail --}}
                                <td class="px-4 py-3">
                                    @if ($book->cover_thumb_url ?? $book->cover_url)
                                        <img
                                            src="{{ $book->cover_thumb_url ?? $book->cover_url }}"
                                            alt="{{ $book->title }}"
                                            class="w-10 h-14 object-cover rounded"
                                        >
                                    @else
                                        <div class="w-10 h-14 bg-surface-muted border border-border-subtle rounded flex items-center justify-center">
                                            <svg class="w-4 h-4 text-text-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </td>

                                {{-- Title --}}
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.books.edit', $book) }}" class="font-medium text-text-primary hover:text-brand-700 transition">
                                        {{ $book->title }}
                                    </a>
                                    <p class="text-xs text-text-subtle mt-0.5">{{ $book->slug }}</p>
                                </td>

                                {{-- Price --}}
                                <td class="px-4 py-3 text-text-primary">
                                    {{ number_format($book->price / 100, config('shop.currency_decimals'), config('shop.currency_decimal_sep'), ' ') }} {{ config('shop.currency_symbol') }}
                                </td>

                                {{-- Status toggle --}}
                                <td class="px-4 py-3">
                                    <div
                                        x-data="{
                                            status: '{{ $book->status->value }}',
                                            loading: false,
                                            toggle() {
                                                const previous = this.status;
                                                this.loading = true;
                                                fetch('{{ route('admin.books.toggle-status', $book) }}', {
                                                    method: 'PATCH',
                                                    headers: {
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                        'Accept': 'application/json'
                                                    }
                                                })
                                                .then(r => { if (!r.ok) throw new Error(r.statusText); return r.json(); })
                                                .then(data => { this.status = data.status; })
                                                .catch(() => { this.status = previous; })
                                                .finally(() => { this.loading = false; });
                                            }
                                        }"
                                    >
                                        <button
                                            @click="toggle"
                                            :disabled="loading"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-1"
                                            :class="{
                                                'bg-success-light text-success hover:bg-success-border focus:ring-success': status === 'published',
                                                'bg-surface-muted text-text-muted hover:bg-border-subtle focus:ring-text-subtle': status === 'draft',
                                                'opacity-50 cursor-not-allowed': loading
                                            }"
                                        >
                                            <span
                                                class="w-1.5 h-1.5 rounded-full shrink-0"
                                                :class="{
                                                    'bg-success-dot': status === 'published',
                                                    'bg-text-subtle': status === 'draft'
                                                }"
                                            ></span>
                                            <span x-text="status === 'published' ? 'Опубликована' : 'Черновик'"></span>
                                        </button>
                                    </div>
                                </td>

                                {{-- Featured toggle --}}
                                <td class="px-4 py-3 text-center">
                                    <div
                                        x-data="{
                                            featured: {{ $book->is_featured ? 'true' : 'false' }},
                                            loading: false,
                                            toggle() {
                                                const previous = this.featured;
                                                this.loading = true;
                                                fetch('{{ route('admin.books.toggle-featured', $book) }}', {
                                                    method: 'PATCH',
                                                    headers: {
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                        'Accept': 'application/json'
                                                    }
                                                })
                                                .then(r => { if (!r.ok) throw new Error(r.statusText); return r.json(); })
                                                .then(data => { this.featured = data.is_featured; })
                                                .catch(() => { this.featured = previous; })
                                                .finally(() => { this.loading = false; });
                                            }
                                        }"
                                        class="flex justify-center"
                                    >
                                        <button
                                            @click="toggle"
                                            :disabled="loading"
                                            class="p-1.5 rounded-lg transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-1"
                                            :class="{
                                                'text-brand-700 hover:bg-brand-50': featured,
                                                'text-text-subtle hover:bg-surface-muted': !featured,
                                                'opacity-50 cursor-not-allowed': loading
                                            }"
                                            :aria-label="featured ? 'Убрать из избранного' : 'Добавить в избранное'"
                                        >
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    x-show="featured"
                                                    x-cloak
                                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                                />
                                            </svg>
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    x-show="!featured"
                                                    x-cloak
                                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </td>

                                {{-- Availability toggle --}}
                                <td class="px-4 py-3">
                                    <div
                                        x-data="{
                                            available: {{ $book->is_available ? 'true' : 'false' }},
                                            loading: false,
                                            toggle() {
                                                const previous = this.available;
                                                this.loading = true;
                                                fetch('{{ route('admin.books.toggle-availability', $book) }}', {
                                                    method: 'PATCH',
                                                    headers: {
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                        'Accept': 'application/json'
                                                    }
                                                })
                                                .then(r => { if (!r.ok) throw new Error(r.statusText); return r.json(); })
                                                .then(data => { this.available = data.is_available; })
                                                .catch(() => { this.available = previous; })
                                                .finally(() => { this.loading = false; });
                                            }
                                        }"
                                    >
                                        <button
                                            @click="toggle"
                                            :disabled="loading"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-1"
                                            :class="{
                                                'bg-success-light text-success hover:bg-success-border focus:ring-success': available,
                                                'bg-warning-light text-warning hover:bg-warning-border focus:ring-warning': !available,
                                                'opacity-50 cursor-not-allowed': loading
                                            }"
                                        >
                                            <span
                                                class="w-1.5 h-1.5 rounded-full shrink-0"
                                                :class="{
                                                    'bg-success-dot': available,
                                                    'bg-warning': !available
                                                }"
                                            ></span>
                                            <span x-text="available ? 'В продаже' : 'Снята'"></span>
                                        </button>
                                    </div>
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Edit --}}
                                        <a
                                            href="{{ route('admin.books.edit', $book) }}"
                                            class="p-1.5 text-text-muted hover:text-brand-700 hover:bg-brand-50 rounded-lg transition"
                                            aria-label="Редактировать"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>

                                        {{-- Delete with confirmation --}}
                                        <div
                                            x-data="{ open: false }"
                                        >
                                            <button
                                                @click="open = true"
                                                class="p-1.5 text-text-muted hover:text-error hover:bg-error-light rounded-lg transition"
                                                aria-label="Удалить"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>

                                            {{-- Confirmation modal --}}
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
                                                    <h3 class="font-serif text-lg text-text-primary mb-2">Удалить книгу?</h3>
                                                    <p class="text-sm text-text-muted mb-6">
                                                        «{{ $book->title }}» будет удалена вместе с файлами. Это действие необратимо.
                                                    </p>
                                                    <div class="flex gap-3 justify-end">
                                                        <button
                                                            @click="open = false"
                                                            class="px-4 py-2 text-sm font-medium text-text-primary border border-border-subtle rounded-lg hover:bg-surface-muted transition"
                                                        >
                                                            Отмена
                                                        </button>
                                                        <form method="POST" action="{{ route('admin.books.destroy', $book) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button
                                                                type="submit"
                                                                class="px-4 py-2 text-sm font-medium text-white bg-error-muted hover:bg-error-hover rounded-lg transition focus:outline-none focus:ring-2 focus:ring-error focus:ring-offset-2"
                                                            >
                                                                Удалить
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        @if ($books instanceof \Illuminate\Pagination\LengthAwarePaginator && $books->hasPages())
            <div class="mt-6">
                {{ $books->links() }}
            </div>
        @endif

    @endif

</div>

@endsection
