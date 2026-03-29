@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="mb-8">
        <h1 class="font-serif text-2xl text-text-primary">Добавить книгу</h1>
        <p class="text-sm text-text-muted mt-1">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700 transition">Панель управления</a>
            &rsaquo;
            <a href="{{ route('admin.books.index') }}" class="hover:text-brand-700 transition">Книги</a>
            &rsaquo; Добавить
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <p class="font-medium mb-1">Пожалуйста, исправьте ошибки:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.books.store') }}"
        enctype="multipart/form-data"
        x-data="{
            slugManuallyEdited: false,
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
                    document.getElementById('slug').value = this.slugify(event.target.value);
                }
            }
        }"
    >
        @csrf

        <div class="bg-surface border border-border-subtle rounded-xl divide-y divide-border-subtle">

            {{-- Basic info --}}
            <div class="p-6 space-y-5">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Основное</h2>

                {{-- Title --}}
                <div>
                    <label for="title" class="block text-sm font-medium text-text-primary mb-1.5">
                        Название <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="title"
                        type="text"
                        name="title"
                        value="{{ old('title') }}"
                        @input="onTitleInput($event)"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface placeholder:text-text-subtle transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('title') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                        placeholder="Название книги"
                    >
                    @error('title')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label for="slug" class="block text-sm font-medium text-text-primary mb-1.5">
                        Slug <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="slug"
                        type="text"
                        name="slug"
                        value="{{ old('slug') }}"
                        @input="slugManuallyEdited = $event.target.value.length > 0"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface font-mono placeholder:text-text-subtle transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('slug') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                        placeholder="nazvanie-knigi"
                    >
                    <p class="mt-1 text-xs text-text-subtle">Используется в URL. Автозаполняется из названия.</p>
                    @error('slug')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Price --}}
                <div>
                    <label for="price" class="block text-sm font-medium text-text-primary mb-1.5">
                        Цена (в рублях) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="price"
                            type="number"
                            name="price"
                            value="{{ old('price') }}"
                            min="0"
                            step="0.01"
                            required
                            class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface placeholder:text-text-subtle transition
                                focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                                @error('price') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                            placeholder="499"
                        >
                    </div>
                    <p class="mt-1 text-xs text-text-subtle">Введите сумму в рублях. Например: 499</p>
                    @error('price')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-text-primary mb-1.5">
                        Статус <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="status"
                        name="status"
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('status') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                    >
                        <option value="draft" @selected(old('status', 'draft') === 'draft')>Черновик</option>
                        <option value="published" @selected(old('status') === 'published')>Опубликована</option>
                    </select>
                    @error('status')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
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
                            @error('annotation') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                        placeholder="Краткое описание книги для каталога..."
                    >{{ old('annotation') }}</textarea>
                    @error('annotation')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
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
                            @error('excerpt') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                        placeholder="Короткий отрывок для страницы книги..."
                    >{{ old('excerpt') }}</textarea>
                    @error('excerpt')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
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
                            @error('fragment') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                        placeholder="Ознакомительный фрагмент книги..."
                    >{{ old('fragment') }}</textarea>
                    @error('fragment')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
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
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Cover thumbnail --}}
                <div>
                    <label for="cover_thumb" class="block text-sm font-medium text-text-primary mb-1.5">
                        Миниатюра обложки
                        <span class="text-xs font-normal text-text-subtle ml-1">(jpg, png, webp — до 5 МБ)</span>
                    </label>
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
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Epub --}}
                <div>
                    <label for="epub" class="block text-sm font-medium text-text-primary mb-1.5">
                        Epub файл
                        <span class="text-xs font-normal text-text-subtle ml-1">(до 100 МБ)</span>
                    </label>
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
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
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
                        @checked(old('is_featured'))
                        class="w-4 h-4 rounded border-border-subtle text-brand-600 focus:ring-brand-500 cursor-pointer"
                    >
                    <label for="is_featured" class="text-sm text-text-primary cursor-pointer">
                        В избранном
                        <span class="text-xs text-text-subtle ml-1">— показывать на главной странице</span>
                    </label>
                    @error('is_featured')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
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
                        value="{{ old('sort_order', 0) }}"
                        min="0"
                        class="w-32 px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('sort_order') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                    >
                    <p class="mt-1 text-xs text-text-subtle">Меньшее число — выше в списке.</p>
                    @error('sort_order')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
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
                Создать книгу
            </button>
        </div>

    </form>

</div>

@endsection
