@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="mb-8 pb-6 border-b border-border-subtle">
        <h1 class="font-serif text-3xl text-text-primary mb-1">Личный кабинет</h1>
        <p class="text-sm text-text-muted">{{ auth()->user()->name }} &middot; {{ auth()->user()->email }}</p>
    </div>

    {{-- Placeholder content --}}
    <div class="bg-white border border-border-subtle rounded-xl p-10 text-center">
        <div class="mx-auto w-14 h-14 bg-surface-muted rounded-full flex items-center justify-center mb-4">
            <svg class="w-7 h-7 text-text-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
        </div>
        <p class="font-serif text-lg text-text-primary mb-2">Раздел в разработке</p>
        <p class="text-sm text-text-muted">Здесь появятся ваши книги, история покупок и настройки аккаунта.</p>
    </div>

</div>

@endsection
