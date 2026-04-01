@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    <div class="mb-8">
        <h1 class="font-serif text-3xl text-text-primary">Каталог книг</h1>
        <p class="text-text-muted mt-1 text-sm">Все доступные издания</p>
    </div>

    @if($books->isEmpty())
        <div class="py-20 text-center">
            <p class="font-serif text-xl text-text-muted">Книги скоро появятся</p>
            <p class="text-sm text-text-subtle mt-2">Загляните позже</p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            @foreach($books as $book)
                <x-book-card :book="$book" :owned-book-ids="$ownedBookIds" />
            @endforeach
        </div>
    @endif

</div>

@endsection
