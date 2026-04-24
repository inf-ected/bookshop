{{-- Book files management block — lives OUTSIDE the main book form to avoid nested forms. --}}
{{-- Each action (retry, replace) uses its own standalone <form>. --}}
<div
    class="mt-8 bg-surface border border-border-subtle rounded-xl p-6 space-y-4"
    x-data="{
        files: @js($book->files->map(fn ($f) => [
            'id'            => $f->id,
            'format'        => $f->format->value,
            'format_label'  => $f->format->label(),
            'status'        => $f->status->value,
            'is_source'     => $f->is_source,
            'error_message' => $f->error_message,
            'error_open'    => false,
        ])->values()),
        polling: false,
        pollInterval: null,

        statusLabel(status) {
            const map = { pending: 'Ожидает', processing: 'Конвертируется', ready: 'Готов', failed: 'Ошибка' };
            return map[status] ?? status;
        },

        badgeClass(status) {
            const map = {
                pending:    'bg-yellow-100 text-yellow-800',
                processing: 'bg-blue-100 text-blue-800',
                ready:      'bg-green-100 text-green-800',
                failed:     'bg-red-100 text-red-800',
            };
            return map[status] ?? 'bg-gray-100 text-gray-700';
        },

        needsPolling() {
            return this.files.some(f => f.status === 'pending' || f.status === 'processing');
        },

        startPolling() {
            if (this.polling) { return; }
            this.polling = true;
            this.pollInterval = setInterval(() => { this.fetchStatus(); }, 3000);
        },

        stopPolling() {
            this.polling = false;
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        },

        fetchStatus() {
            fetch('{{ route('admin.books.files.status', $book) }}')
                .then(r => r.json())
                .then(data => {
                    data.forEach(item => {
                        const f = this.files.find(x => x.id === item.id);
                        if (f) {
                            f.status        = item.status;
                            f.error_message = item.error_message;
                        }
                    });
                    if (!this.needsPolling()) { this.stopPolling(); }
                });
        },

        init() {
            if (this.needsPolling()) { this.startPolling(); }
        }
    }"
    x-init="init()"
>
    <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Файлы книги</h2>

    <template x-if="files.length === 0">
        <div class="space-y-3">
            <p class="text-sm text-text-muted">Файлы ещё не загружены.</p>
            <form
                action="{{ route('admin.books.files.store', $book) }}"
                method="POST"
                enctype="multipart/form-data"
                class="flex items-center gap-2"
                @submit="setTimeout(() => { startPolling(); }, 200)"
            >
                @csrf
                <input
                    type="file"
                    name="file"
                    accept=".docx,.epub,.fb2"
                    class="text-xs text-text-muted w-44
                        file:mr-2 file:py-1 file:px-2 file:rounded file:border file:border-border-subtle
                        file:text-xs file:font-medium file:text-text-primary file:bg-surface-muted
                        hover:file:bg-brand-50 hover:file:text-brand-700
                        file:transition file:cursor-pointer cursor-pointer"
                    required
                >
                <button
                    type="submit"
                    class="px-3 py-1 text-xs font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition"
                >
                    Загрузить исходник
                </button>
            </form>
        </div>
    </template>

    <template x-if="files.length > 0">
        <div class="overflow-x-auto rounded-lg border border-border-subtle">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border-subtle bg-surface-muted">
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-text-muted uppercase tracking-wide">Формат</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-text-muted uppercase tracking-wide">Статус</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-text-muted uppercase tracking-wide">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle">
                    <template x-for="f in files" :key="f.id">
                        <tr>
                            <td class="px-4 py-3 text-text-primary font-medium">
                                <span x-text="f.format_label"></span>
                                <template x-if="f.is_source">
                                    <span class="ml-1 text-xs text-text-subtle">(исходник)</span>
                                </template>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="badgeClass(f.status)"
                                    x-text="statusLabel(f.status)"
                                ></span>
                                <template x-if="f.status === 'failed' && f.error_message">
                                    <div class="mt-1">
                                        <button
                                            @click="f.error_open = !f.error_open"
                                            class="text-xs text-error underline focus:outline-none"
                                        >
                                            <span x-text="f.error_open ? 'Скрыть ошибку' : 'Показать ошибку'"></span>
                                        </button>
                                        <div
                                            x-show="f.error_open"
                                            x-transition
                                            class="mt-1 text-xs text-error bg-error-light rounded p-2 font-mono break-all"
                                            x-text="f.error_message"
                                        ></div>
                                    </div>
                                </template>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">

                                    {{-- Download (ready only) --}}
                                    <template x-if="f.status === 'ready'">
                                        <a
                                            :href="'{{ url('admin/books/' . $book->slug . '/files') }}/' + f.id + '/download'"
                                            class="px-3 py-1 text-xs font-medium text-brand-700 border border-brand-300 rounded-lg hover:bg-brand-50 transition"
                                        >
                                            Скачать
                                        </a>
                                    </template>

                                    {{-- Retry (failed derived files only) --}}
                                    <template x-if="f.status === 'failed' && !f.is_source">
                                        <form
                                            :action="'{{ url('admin/books/' . $book->slug . '/files') }}/' + f.id + '/retry'"
                                            method="POST"
                                            @submit="$el.querySelector('button').disabled = true; if (!needsPolling()) { setTimeout(() => { files.find(x => x.id === f.id).status = 'pending'; startPolling(); }, 100); }"
                                        >
                                            @csrf
                                            <button
                                                type="submit"
                                                class="px-3 py-1 text-xs font-medium text-text-primary border border-border-subtle rounded-lg hover:bg-surface-muted transition"
                                            >
                                                Повторить
                                            </button>
                                        </form>
                                    </template>

                                    {{-- Replace derived format --}}
                                    <template x-if="!f.is_source">
                                        <form
                                            action="{{ route('admin.books.files.store', $book) }}"
                                            method="POST"
                                            enctype="multipart/form-data"
                                            class="flex items-center gap-1"
                                            @submit="if (!needsPolling()) { setTimeout(() => { startPolling(); }, 200); }"
                                        >
                                            @csrf
                                            <input type="hidden" name="format" :value="f.format">
                                            <input
                                                type="file"
                                                name="file"
                                                :accept="'.' + f.format"
                                                class="text-xs text-text-muted w-36
                                                    file:mr-2 file:py-1 file:px-2 file:rounded file:border file:border-border-subtle
                                                    file:text-xs file:font-medium file:text-text-primary file:bg-surface-muted
                                                    hover:file:bg-brand-50 hover:file:text-brand-700
                                                    file:transition file:cursor-pointer cursor-pointer"
                                                required
                                            >
                                            <button
                                                type="submit"
                                                class="px-2 py-1 text-xs font-medium text-text-primary border border-border-subtle rounded hover:bg-surface-muted transition"
                                            >
                                                Заменить
                                            </button>
                                        </form>
                                    </template>

                                    {{-- Replace source file --}}
                                    <template x-if="f.is_source">
                                        <form
                                            action="{{ route('admin.books.files.store', $book) }}"
                                            method="POST"
                                            enctype="multipart/form-data"
                                            class="flex items-center gap-1"
                                            @submit="if (!needsPolling()) { setTimeout(() => { startPolling(); }, 200); }"
                                        >
                                            @csrf
                                            <input
                                                type="file"
                                                name="file"
                                                accept=".docx,.epub,.fb2"
                                                class="text-xs text-text-muted w-36
                                                    file:mr-2 file:py-1 file:px-2 file:rounded file:border file:border-border-subtle
                                                    file:text-xs file:font-medium file:text-text-primary file:bg-surface-muted
                                                    hover:file:bg-brand-50 hover:file:text-brand-700
                                                    file:transition file:cursor-pointer cursor-pointer"
                                                required
                                            >
                                            <button
                                                type="submit"
                                                class="px-2 py-1 text-xs font-medium text-text-primary border border-border-subtle rounded hover:bg-surface-muted transition"
                                            >
                                                Заменить
                                            </button>
                                        </form>
                                    </template>

                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    {{-- Polling indicator --}}
    <template x-if="polling">
        <p class="text-xs text-text-muted flex items-center gap-1.5">
            <svg class="animate-spin w-3.5 h-3.5 text-brand-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            Обновление статусов...
        </p>
    </template>

</div>
