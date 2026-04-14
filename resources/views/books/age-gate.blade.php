@extends('layouts.app')

@section('title', 'Контент для взрослых — ' . $book->title)

@section('content')
    <div class="min-h-[60vh] flex items-center justify-center px-4 py-16">
        <div class="w-full max-w-md bg-surface rounded-xl shadow-lg border border-border-subtle p-8 flex flex-col gap-5 text-center">

            <div>
                <span class="inline-block mb-3 px-3 py-1 text-sm font-bold text-white bg-error rounded-full">
                    18+
                </span>
                <h1 class="font-serif text-2xl text-text-primary leading-snug">
                    Контент для взрослых
                </h1>
            </div>

            <p class="text-sm text-text-muted leading-relaxed">
                Книга <strong class="text-text-primary">{{ $book->title }}</strong> содержит материалы для взрослых.
                Пожалуйста, подтвердите свой возраст.
            </p>

            <div class="flex flex-col sm:flex-row gap-3">
                <form method="POST" action="{{ route('age-verification.store') }}" class="flex-1">
                    @csrf
        <button
                        type="submit"
                        class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-brand-700 hover:bg-brand-800 rounded-lg transition-colors"
                    >
                        Мне есть 18 лет
                    </button>
                </form>
                <a
                    href="{{ route('books.index') }}"
                    class="flex-1 inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-text-muted border border-border-subtle hover:bg-surface-muted rounded-lg transition-colors"
                >
                    Назад в каталог
                </a>
            </div>

        </div>
    </div>
@endsection
