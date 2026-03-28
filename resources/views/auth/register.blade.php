@extends('layouts.app')

@section('content')

<div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">

        <div class="text-center mb-8">
            <h1 class="font-serif text-3xl text-text-primary mb-2">Регистрация</h1>
            <p class="text-sm text-text-muted">
                Уже есть аккаунт?
                <a href="{{ route('login') }}" class="text-brand-700 hover:text-brand-900 transition underline">
                    Войти
                </a>
            </p>
        </div>

        <div class="bg-white border border-border-subtle rounded-xl shadow-sm p-8">

            {{-- General error --}}
            @if (session('error'))
                <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="space-y-5">
                @csrf

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-sans font-medium text-text-primary mb-1.5">
                        Имя
                    </label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        autocomplete="name"
                        class="w-full px-3.5 py-2.5 rounded-lg border text-sm font-sans text-text-primary bg-white placeholder:text-text-subtle transition
                            focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                            @error('name') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                        placeholder="Как вас зовут"
                    >
                    @error('name')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

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

                {{-- Password --}}
                <div x-data="{ showPassword: false }">
                    <label for="password" class="block text-sm font-sans font-medium text-text-primary mb-1.5">
                        Пароль
                    </label>
                    <div class="relative">
                        <input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            required
                            autocomplete="new-password"
                            class="w-full px-3.5 py-2.5 pr-11 rounded-lg border text-sm font-sans text-text-primary bg-white transition
                                focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent
                                @error('password') border-red-400 bg-red-50 @else border-border-subtle @enderror"
                        >
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-text-subtle hover:text-text-muted transition"
                            :aria-label="showPassword ? 'Скрыть пароль' : 'Показать пароль'"
                        >
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password confirmation --}}
                <div x-data="{ showConfirm: false }">
                    <label for="password_confirmation" class="block text-sm font-sans font-medium text-text-primary mb-1.5">
                        Подтвердите пароль
                    </label>
                    <div class="relative">
                        <input
                            id="password_confirmation"
                            :type="showConfirm ? 'text' : 'password'"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            class="w-full px-3.5 py-2.5 pr-11 rounded-lg border border-border-subtle text-sm font-sans text-text-primary bg-white transition
                                focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                        >
                        <button
                            type="button"
                            @click="showConfirm = !showConfirm"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-text-subtle hover:text-text-muted transition"
                            :aria-label="showConfirm ? 'Скрыть пароль' : 'Показать пароль'"
                        >
                            <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    @error('password_confirmation')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Terms checkbox --}}
                <div class="space-y-3 pt-1">
                    <div class="flex items-start gap-2.5">
                        <input
                            id="terms"
                            type="checkbox"
                            name="terms"
                            required
                            class="mt-0.5 w-4 h-4 rounded border-border-subtle text-brand-600 focus:ring-brand-500 shrink-0
                                @error('terms') border-red-400 @endif"
                            {{ old('terms') ? 'checked' : '' }}
                        >
                        <label for="terms" class="text-sm font-sans text-text-muted leading-snug">
                            Я принимаю
                            <a href="{{ route('static.terms') }}" target="_blank" class="text-brand-700 hover:text-brand-900 underline transition">
                                пользовательское соглашение
                            </a>
                            и
                            <a href="{{ route('static.privacy') }}" target="_blank" class="text-brand-700 hover:text-brand-900 underline transition">
                                политику конфиденциальности
                            </a>
                        </label>
                    </div>
                    @error('terms')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    {{-- Newsletter consent --}}
                    <div class="flex items-start gap-2.5">
                        <input
                            id="newsletter_consent"
                            type="checkbox"
                            name="newsletter_consent"
                            value="1"
                            class="mt-0.5 w-4 h-4 rounded border-border-subtle text-brand-600 focus:ring-brand-500 shrink-0"
                            {{ old('newsletter_consent') ? 'checked' : '' }}
                        >
                        <label for="newsletter_consent" class="text-sm font-sans text-text-muted leading-snug">
                            Я хочу получать новости о новых книгах
                        </label>
                    </div>
                </div>

                {{-- Submit --}}
                <button
                    type="submit"
                    class="w-full px-4 py-2.5 bg-brand-700 hover:bg-brand-900 text-white font-sans text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                >
                    Зарегистрироваться
                </button>

            </form>

            {{-- Divider --}}
            <div class="flex items-center gap-4 my-6">
                <div class="flex-1 h-px bg-border-subtle"></div>
                <span class="text-xs font-sans text-text-subtle">Или войдите через:</span>
                <div class="flex-1 h-px bg-border-subtle"></div>
            </div>

            {{-- OAuth buttons --}}
            <div class="grid grid-cols-2 gap-3">

                {{-- Google --}}
                <a
                    href="{{ route('auth.oauth.redirect', ['provider' => 'google']) }}"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 border border-border-subtle rounded-lg bg-white hover:bg-surface-muted transition text-sm font-sans text-text-primary"
                >
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    <span>Google</span>
                </a>

                {{-- VK --}}
                <a
                    href="{{ route('auth.oauth.redirect', ['provider' => 'vk']) }}"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 border border-border-subtle rounded-lg bg-white hover:bg-surface-muted transition text-sm font-sans text-text-primary"
                >
                    <svg class="w-4 h-4 shrink-0" fill="#0077FF" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15.684 0H8.316C1.592 0 0 1.592 0 8.316v7.368C0 22.408 1.592 24 8.316 24h7.368C22.408 24 24 22.408 24 15.684V8.316C24 1.592 22.408 0 15.684 0zm3.692 17.123h-1.744c-.66 0-.862-.525-2.049-1.714-1.033-1-1.49-1.135-1.744-1.135-.356 0-.458.102-.458.593v1.566c0 .423-.135.677-1.253.677-1.846 0-3.896-1.118-5.335-3.202C4.624 10.857 4.03 8.57 4.03 8.096c0-.254.102-.491.593-.491h1.744c.441 0 .61.203.78.677.863 2.49 2.303 4.675 2.896 4.675.22 0 .322-.102.322-.66V9.721c-.068-1.186-.695-1.287-.695-1.71 0-.204.17-.407.44-.407h2.744c.373 0 .508.203.508.643v3.473c0 .372.17.508.271.508.22 0 .407-.136.813-.542 1.253-1.406 2.151-3.574 2.151-3.574.119-.254.322-.491.763-.491h1.744c.525 0 .644.27.525.643-.22 1.017-2.354 4.031-2.354 4.031-.186.305-.254.44 0 .78.186.254.796.779 1.203 1.253.745.847 1.32 1.558 1.473 2.049.17.474-.085.711-.609.711z"/>
                    </svg>
                    <span>ВКонтакте</span>
                </a>

                {{-- Instagram --}}
                <a
                    href="{{ route('auth.oauth.redirect', ['provider' => 'instagram']) }}"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 border border-border-subtle rounded-lg bg-white hover:bg-surface-muted transition text-sm font-sans text-text-primary"
                >
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                        <defs>
                            <linearGradient id="ig-grad-register" x1="0%" y1="100%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#f09433"/>
                                <stop offset="25%" style="stop-color:#e6683c"/>
                                <stop offset="50%" style="stop-color:#dc2743"/>
                                <stop offset="75%" style="stop-color:#cc2366"/>
                                <stop offset="100%" style="stop-color:#bc1888"/>
                            </linearGradient>
                        </defs>
                        <path fill="url(#ig-grad-register)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                    <span>Instagram</span>
                </a>

                {{-- Facebook --}}
                <a
                    href="{{ route('auth.oauth.redirect', ['provider' => 'facebook']) }}"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 border border-border-subtle rounded-lg bg-white hover:bg-surface-muted transition text-sm font-sans text-text-primary"
                >
                    <svg class="w-4 h-4 shrink-0" fill="#1877F2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    <span>Facebook</span>
                </a>

            </div>

        </div>
    </div>
</div>

@endsection
