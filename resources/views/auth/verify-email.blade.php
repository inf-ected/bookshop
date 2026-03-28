@extends('layouts.app')

@section('content')

<div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">

        <div class="text-center mb-8">
            <div class="mx-auto w-16 h-16 bg-brand-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h1 class="font-serif text-3xl text-text-primary mb-2">Подтвердите email</h1>
            <p class="text-sm text-text-muted max-w-sm mx-auto">
                Пожалуйста, подтвердите ваш email — мы отправили письмо со ссылкой на указанный адрес.
                Если письмо не пришло, проверьте папку «Спам».
            </p>
        </div>

        <div class="bg-white border border-border-subtle rounded-xl shadow-sm p-8">

            {{-- Success: link resent --}}
            @if (session('status') === 'verification-link-sent')
                <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                    Новая ссылка для подтверждения отправлена на ваш email.
                </div>
            @endif

            {{-- Resend form --}}
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button
                    type="submit"
                    class="w-full px-4 py-2.5 bg-brand-700 hover:bg-brand-900 text-white font-sans text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                >
                    Отправить повторно
                </button>
            </form>

            {{-- Logout --}}
            <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
                @csrf
                <button
                    type="submit"
                    class="text-sm font-sans text-text-muted hover:text-text-primary transition underline"
                >
                    Выйти из аккаунта
                </button>
            </form>

        </div>
    </div>
</div>

@endsection
