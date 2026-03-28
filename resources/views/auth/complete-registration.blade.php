@extends('layouts.app')

@section('content')

<div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">

        <div class="text-center mb-8">
            <h1 class="font-serif text-3xl text-text-primary mb-2">Почти готово</h1>
            <p class="text-sm text-text-muted max-w-sm mx-auto">
                Провайдер авторизации не передал email-адрес. Укажите его, чтобы завершить регистрацию.
            </p>
        </div>

        <div class="bg-white border border-border-subtle rounded-xl shadow-sm p-8">

            {{-- General error --}}
            @if (session('error'))
                <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('auth.complete-registration.store') }}" class="space-y-5">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-sans font-medium text-text-primary mb-1.5">
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm font-sans text-text-primary bg-white placeholder:text-text-subtle transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('email') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                        placeholder="you@example.com"
                    >
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <button
                    type="submit"
                    class="w-full px-4 py-2.5 bg-brand-700 hover:bg-brand-900 text-white font-sans text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                >
                    Продолжить
                </button>

            </form>

        </div>
    </div>
</div>

@endsection
