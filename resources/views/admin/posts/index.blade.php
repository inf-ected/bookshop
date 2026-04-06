@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="font-serif text-2xl text-text-primary">Статьи</h1>
            <p class="text-sm text-text-muted mt-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700 transition">Панель управления</a>
                &rsaquo; Статьи
            </p>
        </div>
        <a
            href="{{ route('admin.posts.create') }}"
            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-700 hover:bg-brand-900 text-white text-sm font-sans font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Написать статью
        </a>
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

    @if ($posts->isEmpty())

        <div class="bg-surface border border-border-subtle rounded-xl py-16 text-center">
            <svg class="w-12 h-12 text-text-subtle mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="font-serif text-lg text-text-muted mb-2">Статей пока нет</p>
            <p class="text-sm text-text-subtle mb-6">Напишите первую статью для блога</p>
            <a
                href="{{ route('admin.posts.create') }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-brand-700 hover:bg-brand-900 text-white text-sm font-sans font-medium rounded-lg transition"
            >
                Написать статью
            </a>
        </div>

    @else

        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm font-sans">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-muted">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider">
                                Заголовок
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-32">
                                Статус
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-text-muted uppercase tracking-wider w-40">
                                Опубликована
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-text-muted uppercase tracking-wider w-28">
                                Действия
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach ($posts as $post)
                            <tr class="hover:bg-surface-muted transition">

                                {{-- Title --}}
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.posts.edit', $post) }}" class="font-medium text-text-primary hover:text-brand-700 transition">
                                        {{ $post->title }}
                                    </a>
                                    <p class="text-xs text-text-subtle mt-0.5">{{ $post->slug }}</p>
                                </td>

                                {{-- Status toggle --}}
                                <td class="px-4 py-3">
                                    <div
                                        x-data="{
                                            status: '{{ $post->status->value }}',
                                            loading: false,
                                            toggle() {
                                                const previous = this.status;
                                                this.loading = true;
                                                fetch('{{ route('admin.posts.toggle-status', $post) }}', {
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

                                {{-- Published at --}}
                                <td class="px-4 py-3 text-text-muted text-xs">
                                    {{ $post->published_at ? $post->published_at->format('d.m.Y H:i') : '—' }}
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">

                                        {{-- Edit --}}
                                        <a
                                            href="{{ route('admin.posts.edit', $post) }}"
                                            class="p-1.5 text-text-muted hover:text-brand-700 hover:bg-brand-50 rounded-lg transition"
                                            aria-label="Редактировать"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>

                                        {{-- Delete --}}
                                        <div x-data="{ open: false }">
                                            <button
                                                @click="open = true"
                                                class="p-1.5 text-text-muted hover:text-error hover:bg-error-light rounded-lg transition"
                                                aria-label="Удалить"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>

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
                                                    <h3 class="font-serif text-lg text-text-primary mb-2">Удалить статью?</h3>
                                                    <p class="text-sm text-text-muted mb-6">
                                                        «{{ $post->title }}» будет удалена безвозвратно.
                                                    </p>
                                                    <div class="flex gap-3 justify-end">
                                                        <button
                                                            @click="open = false"
                                                            class="px-4 py-2 text-sm font-medium text-text-primary border border-border-subtle rounded-lg hover:bg-surface-muted transition"
                                                        >
                                                            Отмена
                                                        </button>
                                                        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}">
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

        @if ($posts->hasPages())
            <div class="mt-6">
                {{ $posts->links() }}
            </div>
        @endif

    @endif

</div>

@endsection
