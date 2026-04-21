@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="mb-8">
        <h1 class="font-serif text-2xl text-text-primary">Редактировать книгу</h1>
        <p class="text-sm text-text-muted mt-1">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700 transition">Панель управления</a>
            &rsaquo;
            <a href="{{ route('admin.books.index') }}" class="hover:text-brand-700 transition">Книги</a>
            &rsaquo; {{ $book->title }}
        </p>
    </div>

    {{-- Flash success --}}
    @if (session('success'))
        <div class="mb-6 px-4 py-3 bg-success-light border border-success-border rounded-lg text-sm text-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 px-4 py-3 bg-error-light border border-error-border rounded-lg text-sm text-error">
            <p class="font-medium mb-1">Пожалуйста, исправьте ошибки:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs opacity-75">Загруженные файлы (обложка, epub) были сброшены браузером — прикрепите их повторно перед отправкой.</p>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.books.update', $book) }}"
        enctype="multipart/form-data"
        {{-- NOTE: The transliterate/slugify logic below is duplicated in create.blade.php.
             This is intentional until a shared Alpine.js module (e.g. via @js or a
             dedicated JS file loaded via @vite) is introduced. --}}
        x-data="{
            slugManuallyEdited: true,
            transliterate(text) {
                const map = {
                    'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo',
                    'ж':'zh','з':'z','и':'i','й':'j','к':'k','л':'l','м':'m',
                    'н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u',
                    'ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'shch',
                    'ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya',
                    'А':'A','Б':'B','В':'V','Г':'G','Д':'D','Е':'E','Ё':'Yo',
                    'Ж':'Zh','З':'Z','И':'I','Й':'J','К':'K','Л':'L','М':'M',
                    'Н':'N','О':'O','П':'P','Р':'R','С':'S','Т':'T','У':'U',
                    'Ф':'F','Х':'H','Ц':'Ts','Ч':'Ch','Ш':'Sh','Щ':'Shch',
                    'Ъ':'','Ы':'Y','Ь':'','Э':'E','Ю':'Yu','Я':'Ya'
                };
                return text.split('').map(c => map[c] !== undefined ? map[c] : c).join('');
            },
            slugify(text) {
                return this.transliterate(text)
                    .toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .trim()
                    .replace(/[\s_]+/g, '-')
                    .replace(/-+/g, '-');
            },
            onTitleInput(event) {
                if (!this.slugManuallyEdited) {
                    this.$refs.slug.value = this.slugify(event.target.value);
                }
            }
        }"
    >
        @csrf
        @method('PUT')

        <div class="bg-surface border border-border-subtle rounded-xl divide-y divide-border-subtle">

            {{-- Basic info --}}
            <div class="p-6 space-y-5">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Основное</h2>

                {{-- Title --}}
                <div>
                    <label for="title" class="block text-sm font-medium text-text-primary mb-1.5">
                        Название <span class="text-error">*</span>
                    </label>
                    <input
                        id="title"
                        type="text"
                        name="title"
                        value="{{ old('title', $book->title) }}"
                        @input="onTitleInput($event)"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface placeholder:text-text-subtle transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('title') border-error-dot bg-error-light @else border-border-subtle @enderror"
                    >
                    @error('title')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label for="slug" class="block text-sm font-medium text-text-primary mb-1.5">
                        Slug <span class="text-error">*</span>
                    </label>
                    <input
                        id="slug"
                        x-ref="slug"
                        type="text"
                        name="slug"
                        value="{{ old('slug', $book->slug) }}"
                        @input="slugManuallyEdited = true"
                        pattern="[a-zA-Z0-9_\-]+"
                        title="Только латинские буквы, цифры, дефисы и подчёркивания"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface font-mono placeholder:text-text-subtle transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('slug') border-error-dot bg-error-light @else border-border-subtle @enderror"
                    >
                    <p class="mt-1 text-xs text-text-subtle">Используется в URL книги.</p>
                    @error('slug')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Price --}}
                <div>
                    <label for="price" class="block text-sm font-medium text-text-primary mb-1.5">
                        Цена ({{ config('shop.currency_symbol') }}) <span class="text-error">*</span>
                    </label>
                    <input
                        id="price"
                        type="number"
                        name="price"
                        value="{{ old('price', $book->price / 100) }}"
                        min="0"
                        step="0.01"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface placeholder:text-text-subtle transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('price') border-error-dot bg-error-light @else border-border-subtle @enderror"
                    >
                    <p class="mt-1 text-xs text-text-subtle">
                        Текущая цена: {{ number_format($book->price / 100, config('shop.currency_decimals'), config('shop.currency_decimal_sep'), ' ') }} {{ config('shop.currency_symbol') }}
                    </p>
                    @error('price')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-text-primary mb-1.5">
                        Статус <span class="text-error">*</span>
                    </label>
                    <select
                        id="status"
                        name="status"
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('status') border-error-dot bg-error-light @else border-border-subtle @enderror"
                    >
                        <option value="draft" @selected(old('status', $book->status->value) === 'draft')>Черновик</option>
                        <option value="published" @selected(old('status', $book->status->value) === 'published')>Опубликована</option>
                    </select>
                    @error('status')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            {{-- Text content --}}
            <div class="p-6 space-y-5">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Текстовый контент</h2>

                {{-- Annotation --}}
                <div>
                    <label for="annotation" class="block text-sm font-medium text-text-primary mb-1.5">
                        Аннотация
                    </label>
                    <textarea
                        id="annotation"
                        name="annotation"
                        rows="4"
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface placeholder:text-text-subtle transition resize-y
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('annotation') border-error-dot bg-error-light @else border-border-subtle @enderror"
                    >{{ old('annotation', $book->annotation) }}</textarea>
                    @error('annotation')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Excerpt --}}
                <div>
                    <label for="excerpt" class="block text-sm font-medium text-text-primary mb-1.5">
                        Отрывок
                    </label>
                    <textarea
                        id="excerpt"
                        name="excerpt"
                        rows="6"
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface placeholder:text-text-subtle transition resize-y
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('excerpt') border-error-dot bg-error-light @else border-border-subtle @enderror"
                    >{{ old('excerpt', $book->excerpt) }}</textarea>
                    @error('excerpt')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Fragment --}}
                <div>
                    <label for="fragment" class="block text-sm font-medium text-text-primary mb-1.5">
                        Фрагмент
                        <span class="text-xs font-normal text-text-subtle ml-1">(для страницы ознакомительного чтения)</span>
                    </label>
                    <textarea
                        id="fragment"
                        name="fragment"
                        rows="12"
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface placeholder:text-text-subtle transition resize-y
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('fragment') border-error-dot bg-error-light @else border-border-subtle @enderror"
                    >{{ old('fragment', $book->fragment) }}</textarea>
                    @error('fragment')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            {{-- Files --}}
            <div class="p-6 space-y-5">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Файлы</h2>

                {{-- Cover --}}
                <div>
                    <label for="cover" class="block text-sm font-medium text-text-primary mb-1.5">
                        Обложка
                        <span class="text-xs font-normal text-text-subtle ml-1">(jpg, png, webp — до 5 МБ)</span>
                    </label>
                    @if ($book->cover_url)
                        <div class="mb-3 flex items-start gap-4">
                            <img
                                src="{{ $book->cover_url }}"
                                alt="Текущая обложка"
                                class="w-20 h-28 object-cover rounded-lg border border-border-subtle"
                            >
                            <div class="text-xs text-text-muted pt-1">
                                <p class="font-medium text-text-primary mb-0.5">Текущая обложка</p>
                                <p>Загрузите новый файл, чтобы заменить.</p>
                            </div>
                        </div>
                    @endif
                    <input
                        id="cover"
                        type="file"
                        name="cover"
                        accept="image/jpeg,image/png,image/webp"
                        class="w-full text-sm text-text-muted
                            file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border file:border-border-subtle
                            file:text-sm file:font-medium file:text-text-primary file:bg-surface-muted
                            hover:file:bg-brand-50 hover:file:border-brand-300 hover:file:text-brand-700
                            file:transition file:cursor-pointer cursor-pointer"
                    >
                    @error('cover')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Cover thumbnail --}}
                <div>
                    <label for="cover_thumb" class="block text-sm font-medium text-text-primary mb-1.5">
                        Миниатюра обложки
                        <span class="text-xs font-normal text-text-subtle ml-1">(jpg, png, webp — до 2 МБ)</span>
                    </label>
                    @if ($book->cover_thumb_url)
                        <div class="mb-3 flex items-start gap-4">
                            <img
                                src="{{ $book->cover_thumb_url }}"
                                alt="Текущая миниатюра"
                                class="w-10 h-14 object-cover rounded border border-border-subtle"
                            >
                            <div class="text-xs text-text-muted pt-1">
                                <p class="font-medium text-text-primary mb-0.5">Текущая миниатюра</p>
                                <p>Загрузите новый файл, чтобы заменить.</p>
                            </div>
                        </div>
                    @endif
                    <input
                        id="cover_thumb"
                        type="file"
                        name="cover_thumb"
                        accept="image/jpeg,image/png,image/webp"
                        class="w-full text-sm text-text-muted
                            file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border file:border-border-subtle
                            file:text-sm file:font-medium file:text-text-primary file:bg-surface-muted
                            hover:file:bg-brand-50 hover:file:border-brand-300 hover:file:text-brand-700
                            file:transition file:cursor-pointer cursor-pointer"
                    >
                    @error('cover_thumb')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Epub --}}
                <div>
                    <label for="epub" class="block text-sm font-medium text-text-primary mb-1.5">
                        Epub файл
                        <span class="text-xs font-normal text-text-subtle ml-1">(до 100 МБ)</span>
                    </label>
                    {{-- TODO Phase 13.5: show book_files status block here --}}
                    <input
                        id="epub"
                        type="file"
                        name="epub"
                        accept=".epub"
                        class="w-full text-sm text-text-muted
                            file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border file:border-border-subtle
                            file:text-sm file:font-medium file:text-text-primary file:bg-surface-muted
                            hover:file:bg-brand-50 hover:file:border-brand-300 hover:file:text-brand-700
                            file:transition file:cursor-pointer cursor-pointer"
                    >
                    @error('epub')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            {{-- Extra settings --}}
            <div class="p-6 space-y-5">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Дополнительно</h2>

                {{-- Featured --}}
                <div class="flex items-center gap-3">
                    <input
                        id="is_featured"
                        type="checkbox"
                        name="is_featured"
                        value="1"
                        @checked(old('is_featured', $book->is_featured))
                        class="w-4 h-4 rounded border-border-subtle text-brand-600 focus:ring-brand-500 cursor-pointer"
                    >
                    <label for="is_featured" class="text-sm text-text-primary cursor-pointer">
                        В избранном
                        <span class="text-xs text-text-subtle ml-1">— показывать на главной странице</span>
                    </label>
                    @error('is_featured')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Adult content --}}
                <div class="flex items-center gap-3">
                    <input
                        id="is_adult"
                        type="checkbox"
                        name="is_adult"
                        value="1"
                        @checked(old('is_adult', $book->is_adult))
                        class="w-4 h-4 rounded border-border-subtle text-brand-600 focus:ring-brand-500 cursor-pointer"
                    >
                    <label for="is_adult" class="text-sm text-text-primary cursor-pointer">
                        Контент 18+
                        <span class="text-xs text-text-subtle ml-1">— требует подтверждения возраста</span>
                    </label>
                    @error('is_adult')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Sort order --}}
                <div>
                    <label for="sort_order" class="block text-sm font-medium text-text-primary mb-1.5">
                        Порядок сортировки
                    </label>
                    <input
                        id="sort_order"
                        type="number"
                        name="sort_order"
                        value="{{ old('sort_order', $book->sort_order) }}"
                        min="0"
                        class="w-32 px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('sort_order') border-error-dot bg-error-light @else border-border-subtle @enderror"
                    >
                    <p class="mt-1 text-xs text-text-subtle">Меньшее число — выше в списке.</p>
                    @error('sort_order')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

            </div>

        </div>

        {{-- Form actions --}}
        <div class="flex items-center justify-between mt-6">
            <a
                href="{{ route('admin.books.index') }}"
                class="px-4 py-2.5 text-sm font-medium text-text-primary border border-border-subtle rounded-lg hover:bg-surface-muted transition"
            >
                Отмена
            </a>
            <button
                type="submit"
                class="px-6 py-2.5 bg-brand-700 hover:bg-brand-900 text-white text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
            >
                Сохранить изменения
            </button>
        </div>

    </form>

    {{-- Delete section --}}
    <div
        class="mt-10 border border-error-border rounded-xl p-6"
        x-data="{ open: false }"
    >
        <h2 class="text-sm font-sans font-semibold text-text-primary mb-1">Удалить книгу</h2>
        <p class="text-sm text-text-muted mb-4">
            Книга «{{ $book->title }}» и все связанные файлы будут удалены без возможности восстановления.
        </p>
        <button
            @click="open = true"
            class="px-4 py-2 text-sm font-medium text-error border border-error-border rounded-lg hover:bg-error-light transition focus:outline-none focus:ring-2 focus:ring-error focus:ring-offset-2"
        >
            Удалить книгу
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

@endsection
