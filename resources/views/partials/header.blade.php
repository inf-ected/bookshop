<header class="bg-brand-950 text-white">
    <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">

        {{-- Logo --}}
        <a href="{{ route('home') }}" class="font-serif text-xl tracking-wide hover:text-brand-200 transition">
            Книжная лавка
        </a>

        {{-- Desktop nav --}}
        <nav class="hidden md:flex items-center gap-6 text-sm font-sans">
            <a href="{{ route('books.index') }}" class="hover:text-brand-200 transition">Книги</a>
            {{-- TODO Phase 3: convert to named routes when auth routes are implemented --}}
            @auth
                <a href="/cabinet" class="hover:text-brand-200 transition">Личный кабинет</a>
            @else
                <a href="/login" class="hover:text-brand-200 transition">Войти</a>
                <a href="/register" class="px-4 py-1.5 border border-brand-300 rounded hover:bg-brand-800 transition text-sm">
                    Регистрация
                </a>
            @endauth
        </nav>

        {{-- Burger button (mobile) --}}
        <div class="md:hidden" x-data="{ open: false }">
            <button
                @click="open = !open"
                class="p-2 rounded hover:bg-brand-800 transition"
                :aria-expanded="open"
                aria-label="Меню"
            >
                <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            {{-- Mobile dropdown --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                @click.outside="open = false"
                class="absolute top-16 left-0 right-0 bg-brand-950 border-t border-brand-800 z-50 shadow-lg"
            >
                <nav class="flex flex-col px-4 py-3 gap-1 text-sm font-sans">
                    <a href="{{ route('books.index') }}" class="py-2.5 border-b border-brand-800 hover:text-brand-200 transition">
                        Книги
                    </a>
                    {{-- TODO Phase 3: convert to named routes when auth routes are implemented --}}
                    @auth
                        <a href="/cabinet" class="py-2.5 border-b border-brand-800 hover:text-brand-200 transition">
                            Личный кабинет
                        </a>
                    @else
                        <a href="/login" class="py-2.5 border-b border-brand-800 hover:text-brand-200 transition">
                            Войти
                        </a>
                        <a href="/register" class="py-2.5 hover:text-brand-200 transition">
                            Регистрация
                        </a>
                    @endauth
                </nav>
            </div>
        </div>

    </div>
</header>
