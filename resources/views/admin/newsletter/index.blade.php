@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto px-4 py-10">

    <div class="mb-8">
        <h1 class="font-serif text-2xl text-text-primary">Рассылка</h1>
        <p class="text-sm text-text-muted mt-1">Управление email-рассылкой через Resend</p>
    </div>

    @if ($error)
        <div class="mb-6 p-4 bg-warning-light border border-warning-border rounded-lg text-sm text-text-primary">
            {{ $error }}
        </div>
    @endif

    @if (session('success'))
        <div class="mb-6 p-4 bg-success-light border border-success-border rounded-lg text-sm text-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->has('send'))
        <div class="mb-6 p-4 bg-error-light border border-error-border rounded-lg text-sm text-error">
            {{ $errors->first('send') }}
        </div>
    @endif

    {{-- Subscriber count --}}
    <div class="mb-8 p-6 bg-surface border border-border-subtle rounded-lg">
        <p class="text-sm text-text-muted">Подписчиков в аудитории</p>
        <p class="text-3xl font-serif font-semibold text-text-primary mt-1">
            @if ($subscriberCount !== null)
                {{ $subscriberCount }}
            @else
                —
            @endif
        </p>
    </div>

    {{-- Send broadcast form --}}
    <div class="p-6 bg-surface border border-border-subtle rounded-lg">
        <h2 class="font-serif text-lg text-text-primary mb-4">Отправить рассылку</h2>

        <form method="POST" action="{{ route('admin.newsletter.send') }}" class="space-y-4">
            @csrf

            <div>
                <label for="subject" class="block text-sm font-medium text-text-primary mb-1">Тема письма</label>
                <input
                    type="text"
                    id="subject"
                    name="subject"
                    value="{{ old('subject') }}"
                    class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                    required
                    maxlength="255"
                >
                @error('subject')
                    <p class="mt-1 text-xs text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="body" class="block text-sm font-medium text-text-primary mb-1">HTML-содержимое письма</label>
                <textarea
                    id="body"
                    name="body"
                    rows="10"
                    class="w-full rounded-lg border border-border-subtle px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500"
                    required
                >{{ old('body') }}</textarea>
                @error('body')
                    <p class="mt-1 text-xs text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-start gap-2">
                <input
                    type="checkbox"
                    id="confirm_send"
                    name="confirm_send"
                    value="1"
                    class="mt-0.5 rounded border-border-subtle"
                    {{ old('confirm_send') ? 'checked' : '' }}
                >
                <label for="confirm_send" class="text-sm text-text-primary">
                    Я подтверждаю отправку рассылки всем подписчикам аудитории
                </label>
            </div>
            @error('confirm_send')
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror

            <div>
                <button
                    type="submit"
                    class="px-5 py-2 bg-brand-700 hover:bg-brand-900 text-white text-sm font-medium rounded-lg transition"
                >
                    Отправить рассылку
                </button>
            </div>
        </form>
    </div>

</div>

@endsection
