@php
    $currentRoute = request()->route()->getName();
@endphp

<nav class="border-b border-border-subtle mb-8">
    <div class="flex gap-0 overflow-x-auto">

        @unless(auth()->user()?->isAdmin())
        <a
            href="{{ route('cabinet.library') }}"
            class="shrink-0 px-5 py-3 text-sm font-sans border-b-2 transition
                {{ $currentRoute === 'cabinet.library'
                    ? 'border-brand-600 text-brand-700 font-semibold'
                    : 'border-transparent text-text-muted hover:text-text-primary hover:border-border-subtle' }}"
        >
            Библиотека
        </a>

        <a
            href="{{ route('cabinet.orders') }}"
            class="shrink-0 px-5 py-3 text-sm font-sans border-b-2 transition
                {{ $currentRoute === 'cabinet.orders'
                    ? 'border-brand-600 text-brand-700 font-semibold'
                    : 'border-transparent text-text-muted hover:text-text-primary hover:border-border-subtle' }}"
        >
            Заказы
        </a>
        @endunless

        <a
            href="{{ route('cabinet.settings') }}"
            class="shrink-0 px-5 py-3 text-sm font-sans border-b-2 transition
                {{ $currentRoute === 'cabinet.settings'
                    ? 'border-brand-600 text-brand-700 font-semibold'
                    : 'border-transparent text-text-muted hover:text-text-primary hover:border-border-subtle' }}"
        >
            Настройки
        </a>

    </div>
</nav>
