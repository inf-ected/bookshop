@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="mb-8">
        <h1 class="font-serif text-2xl text-text-primary">Редактировать статью</h1>
        <p class="text-sm text-text-muted mt-1">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-700 transition">Панель управления</a>
            &rsaquo;
            <a href="{{ route('admin.posts.index') }}" class="hover:text-brand-700 transition">Статьи</a>
            &rsaquo; {{ $post->title }}
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
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.posts.update', $post) }}"
        enctype="multipart/form-data"
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
                        Заголовок <span class="text-error">*</span>
                    </label>
                    <input
                        id="title"
                        type="text"
                        name="title"
                        value="{{ old('title', $post->title) }}"
                        @input="onTitleInput($event)"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface placeholder:text-text-subtle transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('title') border-error-dot bg-error-light @else border-border-subtle @enderror"
                        placeholder="Заголовок статьи"
                    >
                    @error('title')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label for="slug" class="block text-sm font-medium text-text-primary mb-1.5">
                        URL-адрес (slug) <span class="text-error">*</span>
                    </label>
                    <input
                        id="slug"
                        x-ref="slug"
                        type="text"
                        name="slug"
                        value="{{ old('slug', $post->slug) }}"
                        @input="slugManuallyEdited = $event.target.value.length > 0"
                        pattern="[a-zA-Z0-9_\-]+"
                        title="Только латинские буквы, цифры, дефисы и подчёркивания"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface font-mono placeholder:text-text-subtle transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('slug') border-error-dot bg-error-light @else border-border-subtle @enderror"
                        placeholder="nazvanie-stati"
                    >
                    @error('slug')
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
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('status') border-error-dot bg-error-light @else border-border-subtle @enderror"
                    >
                        <option value="draft" @selected(old('status', $post->status->value) === 'draft')>Черновик</option>
                        <option value="published" @selected(old('status', $post->status->value) === 'published')>Опубликована</option>
                    </select>
                    @error('status')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Published at --}}
                <div>
                    <label for="published_at" class="block text-sm font-medium text-text-primary mb-1.5">
                        Дата публикации
                    </label>
                    <input
                        id="published_at"
                        type="datetime-local"
                        name="published_at"
                        value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('published_at') border-error-dot bg-error-light @else border-border-subtle @enderror"
                    >
                    @error('published_at')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Text content --}}
            <div class="p-6 space-y-5">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Текстовый контент</h2>

                {{-- Excerpt --}}
                <div>
                    <label for="excerpt" class="block text-sm font-medium text-text-primary mb-1.5">
                        Краткое описание <span class="text-error">*</span>
                    </label>
                    <textarea
                        id="excerpt"
                        name="excerpt"
                        rows="3"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface placeholder:text-text-subtle transition resize-y
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('excerpt') border-error-dot bg-error-light @else border-border-subtle @enderror"
                        placeholder="Краткое описание для карточки статьи..."
                    >{{ old('excerpt', $post->excerpt) }}</textarea>
                    @error('excerpt')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Body --}}
                <div>
                    <label for="body" class="block text-sm font-medium text-text-primary mb-1.5">
                        Текст статьи <span class="text-error">*</span>
                        <span class="text-xs font-normal text-text-subtle ml-1">(поддерживается HTML)</span>
                    </label>
                    <textarea
                        id="body"
                        name="body"
                        rows="16"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm text-text-primary bg-surface font-mono placeholder:text-text-subtle transition resize-y
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('body') border-error-dot bg-error-light @else border-border-subtle @enderror"
                        placeholder="&lt;p&gt;Текст статьи...&lt;/p&gt;"
                    >{{ old('body', $post->body) }}</textarea>
                    @error('body')
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Cover image --}}
            <div class="p-6 space-y-5">
                <h2 class="text-xs font-sans font-semibold text-text-muted uppercase tracking-widest">Обложка</h2>

                {{-- Current cover --}}
                @if ($post->cover_url)
                    <div>
                        <p class="text-xs text-text-muted mb-2">Текущая обложка:</p>
                        <img
                            src="{{ $post->cover_url }}"
                            alt="{{ $post->title }}"
                            class="w-40 h-28 object-cover rounded-lg border border-border-subtle"
                        >
                    </div>
                @endif

                <div>
                    <label for="cover" class="block text-sm font-medium text-text-primary mb-1.5">
                        {{ $post->cover_url ? 'Заменить обложку' : 'Загрузить обложку' }}
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
                        <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

        </div>

        {{-- Form actions --}}
        <div class="flex items-center justify-between mt-6">
            <a
                href="{{ route('admin.posts.index') }}"
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

</div>

@endsection
