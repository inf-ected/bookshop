@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="font-serif text-3xl text-text-primary">Настройки</h1>
    </div>

    {{-- Cabinet tab navigation --}}
    <x-cabinet-nav />

    <div class="max-w-xl space-y-8">

        {{-- ── Section 1: Profile ──────────────────────────────────────────── --}}
        <section class="bg-white border border-border-subtle rounded-xl p-6">

            <h2 class="font-serif text-lg text-text-primary mb-5">Профиль</h2>

            @if(session('status') === 'profile-updated')
                <div class="mb-4 px-4 py-3 bg-success-light border border-success-border rounded-lg text-sm text-success font-sans">
                    Профиль успешно обновлён.
                </div>
            @endif

            <form method="POST" action="{{ route('cabinet.settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-sans font-medium text-text-primary mb-1.5">
                        Имя
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $user->name) }}"
                        required
                        class="w-full px-3 py-2 text-sm font-sans border border-border-subtle rounded-lg
                               text-text-primary bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition
                               @error('name') border-error-border @enderror"
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-error font-sans">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email (read-only) --}}
                <div>
                    <label for="email" class="block text-sm font-sans font-medium text-text-primary mb-1.5">
                        Email
                    </label>
                    <input
                        type="email"
                        id="email"
                        value="{{ $user->email }}"
                        readonly
                        disabled
                        class="w-full px-3 py-2 text-sm font-sans border border-border-subtle rounded-lg
                               text-text-muted bg-surface-muted cursor-not-allowed"
                    >
                    <p class="mt-1 text-xs text-text-subtle font-sans">Email изменить нельзя.</p>
                </div>

                <div class="pt-1">
                    <button
                        type="submit"
                        class="px-5 py-2.5 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition font-medium"
                    >
                        Сохранить
                    </button>
                </div>

            </form>

        </section>

        {{-- ── Section 2: Password (only if user has a password) ───────────── --}}
        @if($user->password !== null)
            <section class="bg-white border border-border-subtle rounded-xl p-6">

                <h2 class="font-serif text-lg text-text-primary mb-5">Пароль</h2>

                @if(session('status') === 'password-updated')
                    <div class="mb-4 px-4 py-3 bg-success-light border border-success-border rounded-lg text-sm text-success font-sans">
                        Пароль успешно изменён.
                    </div>
                @endif

                @if($errors->has('current_password') || $errors->has('password'))
                    <div class="mb-4 px-4 py-3 bg-error-light border border-error-border rounded-lg text-sm text-error font-sans">
                        Пожалуйста, исправьте ошибки ниже.
                    </div>
                @endif

                <form method="POST" action="{{ route('cabinet.settings.password') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    {{-- Current password --}}
                    <div>
                        <label for="current_password" class="block text-sm font-sans font-medium text-text-primary mb-1.5">
                            Текущий пароль
                        </label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                            class="w-full px-3 py-2 text-sm font-sans border border-border-subtle rounded-lg
                                   text-text-primary bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition
                                   @error('current_password') border-error-border @enderror"
                        >
                        @error('current_password')
                            <p class="mt-1 text-xs text-error font-sans">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- New password --}}
                    <div>
                        <label for="password" class="block text-sm font-sans font-medium text-text-primary mb-1.5">
                            Новый пароль
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            class="w-full px-3 py-2 text-sm font-sans border border-border-subtle rounded-lg
                                   text-text-primary bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition
                                   @error('password') border-error-border @enderror"
                        >
                        @error('password')
                            <p class="mt-1 text-xs text-error font-sans">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Confirm password --}}
                    <div>
                        <label for="password_confirmation" class="block text-sm font-sans font-medium text-text-primary mb-1.5">
                            Подтвердите пароль
                        </label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            class="w-full px-3 py-2 text-sm font-sans border border-border-subtle rounded-lg
                                   text-text-primary bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                        >
                    </div>

                    <div class="pt-1">
                        <button
                            type="submit"
                            class="px-5 py-2.5 bg-brand-700 text-white font-sans text-sm rounded hover:bg-brand-800 transition font-medium"
                        >
                            Обновить пароль
                        </button>
                    </div>

                </form>

            </section>
        @endif

        {{-- ── Section 3: Newsletter ───────────────────────────────────────── --}}
        <section class="bg-white border border-border-subtle rounded-xl p-6">

            <h2 class="font-serif text-lg text-text-primary mb-1">Рассылка</h2>
            <p class="text-sm text-text-muted font-sans mb-5">Получайте уведомления о новых книгах и акциях.</p>

            @if(session('status') === 'newsletter-subscribed')
                <div class="mb-4 px-4 py-3 bg-success-light border border-success-border rounded-lg text-sm text-success font-sans">
                    Вы подписались на рассылку.
                </div>
            @elseif(session('status') === 'newsletter-unsubscribed')
                <div class="mb-4 px-4 py-3 bg-surface-muted border border-border-subtle rounded-lg text-sm text-text-muted font-sans">
                    Вы отписались от рассылки.
                </div>
            @elseif(session('status') === 'newsletter-error')
                <div class="mb-4 px-4 py-3 bg-error-light border border-error-border rounded-lg text-sm text-error font-sans">
                    Не удалось изменить настройки рассылки. Попробуйте позже.
                </div>
            @endif

            <form method="POST" action="{{ route('cabinet.settings.newsletter') }}" class="flex items-center justify-between">
                @csrf
                <div>
                    <p class="text-sm font-sans font-medium text-text-primary">
                        {{ $user->newsletter_consent ? 'Вы подписаны на рассылку' : 'Вы не подписаны на рассылку' }}
                    </p>
                </div>
                <button
                    type="submit"
                    class="px-4 py-2 text-sm font-sans font-medium rounded-lg transition
                        {{ $user->newsletter_consent
                            ? 'border border-border-subtle text-text-muted hover:text-error hover:border-error-border'
                            : 'bg-brand-700 text-white hover:bg-brand-800' }}"
                >
                    {{ $user->newsletter_consent ? 'Отписаться' : 'Подписаться' }}
                </button>
            </form>

        </section>

        {{-- ── Section 4: OAuth Providers ─────────────────────────────────── --}}
        <section class="bg-white border border-border-subtle rounded-xl p-6">

            <h2 class="font-serif text-lg text-text-primary mb-1">Связанные аккаунты</h2>
            <p class="text-sm text-text-muted font-sans mb-5">Войдите через социальную сеть для быстрого доступа.</p>

            @if(session('status') === 'provider-linked')
                <div class="mb-4 px-4 py-3 bg-success-light border border-success-border rounded-lg text-sm text-success font-sans">
                    Аккаунт успешно подключён.
                </div>
            @endif

            @if(session('status') === 'provider-unlinked')
                <div class="mb-4 px-4 py-3 bg-surface-muted border border-border-subtle rounded-lg text-sm text-text-muted font-sans">
                    Аккаунт отвязан.
                </div>
            @endif

            @if($errors->has('provider'))
                <div class="mb-4 px-4 py-3 bg-error-light border border-error-border rounded-lg text-sm text-error font-sans">
                    {{ $errors->first('provider') }}
                </div>
            @endif

            @php
                $providers = [
                    'google'    => ['name' => 'Google',    'icon' => 'google'],
                    'vk'        => ['name' => 'ВКонтакте', 'icon' => 'vk'],
                    'facebook'  => ['name' => 'Facebook',  'icon' => 'facebook'],
                    'instagram' => ['name' => 'Instagram', 'icon' => 'instagram'],
                ];

                $canUnlink = count($linkedProviders) > 1 || $user->password !== null;
            @endphp

            <ul class="space-y-3">
                @foreach($providers as $providerKey => $providerMeta)
                    @php $isLinked = in_array($providerKey, $linkedProviders); @endphp

                    <li class="flex items-center justify-between gap-4 py-3 border-b border-border-subtle last:border-0">

                        {{-- Provider name + icon --}}
                        <div class="flex items-center gap-3">
                            @if($providerMeta['icon'] === 'google')
                                {{-- Google brand colors: intentional exception — official multi-color logo cannot be represented via semantic tokens --}}
                                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                </svg>
                            @elseif($providerMeta['icon'] === 'vk')
                                <svg class="w-5 h-5 text-text-muted shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12.785 16.241s.288-.032.436-.194c.136-.148.132-.427.132-.427s-.02-1.304.585-1.496c.598-.19 1.365 1.26 2.179 1.815.615.418 1.082.326 1.082.326l2.172-.03s1.136-.071.598-1.023c-.044-.074-.314-.661-1.616-1.869-1.365-1.265-1.182-1.06.462-3.248.999-1.33 1.399-2.142 1.273-2.49-.12-.33-.855-.243-.855-.243l-2.443.015s-.181-.025-.315.056c-.132.079-.216.267-.216.267s-.388 1.036-.907 1.916c-1.094 1.86-1.531 1.96-1.71 1.844-.415-.268-.311-1.075-.311-1.648 0-1.793.272-2.54-.529-2.733-.266-.064-.461-.107-1.141-.114-.872-.009-1.609.003-2.027.207-.278.136-.492.44-.361.457.161.021.527.099.721.363.25.341.241 1.107.241 1.107s.144 2.11-.335 2.372c-.329.179-.78-.186-1.748-1.853-.497-.859-.873-1.81-.873-1.81s-.072-.183-.202-.28c-.157-.119-.377-.156-.377-.156l-2.322.015s-.348.01-.476.161c-.114.135-.009.414-.009.414s1.816 4.249 3.872 6.39c1.886 1.965 4.029 1.836 4.029 1.836h.972z"/>
                                </svg>
                            @elseif($providerMeta['icon'] === 'facebook')
                                <svg class="w-5 h-5 text-text-muted shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                            @elseif($providerMeta['icon'] === 'instagram')
                                <svg class="w-5 h-5 text-text-muted shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                </svg>
                            @endif

                            <span class="text-sm font-sans font-medium text-text-primary">
                                {{ $providerMeta['name'] }}
                            </span>
                        </div>

                        {{-- Linked / Unlinked actions --}}
                        @if($isLinked)
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-sans font-medium bg-success-light text-success border border-success-border">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success-dot inline-block"></span>
                                    Подключён
                                </span>

                                @if($canUnlink)
                                    <form method="POST" action="{{ route('cabinet.settings.oauth.unlink', $providerKey) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="text-xs font-sans text-text-muted hover:text-error transition"
                                        >
                                            Отвязать
                                        </button>
                                    </form>
                                @else
                                    <span
                                        class="text-xs font-sans text-text-subtle cursor-not-allowed"
                                        title="Нельзя отвязать единственный способ входа без пароля"
                                    >
                                        Отвязать
                                    </span>
                                @endif
                            </div>
                        @else
                            <form method="POST" action="{{ route('cabinet.settings.oauth.link', $providerKey) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="px-3 py-1.5 text-xs font-sans border border-brand-700 text-brand-700 rounded hover:bg-brand-50 transition"
                                >
                                    Подключить
                                </button>
                            </form>
                        @endif

                    </li>
                @endforeach
            </ul>

        </section>

    </div>

</div>

@endsection
