<header class="bg-brand-950 text-white">
    <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">

        {{-- Logo --}}
        <a href="{{ route('home') }}" class="font-serif text-xl tracking-wide hover:text-brand-200 transition">
            Книжная лавка
        </a>

        {{-- Desktop nav --}}
        <nav class="hidden md:flex items-center gap-6 text-sm font-sans">
            <a href="{{ route('books.index') }}" class="hover:text-brand-200 transition">Книги</a>

            {{-- Cart icon with badge — hidden for admins --}}
            @unless(auth()->user()?->isAdmin())
            <a href="{{ route('cart.index') }}" class="relative hover:text-brand-200 transition" aria-label="Корзина">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3A1 1 0 006 17h12M9 21a1 1 0 100-2 1 1 0 000 2zm10 0a1 1 0 100-2 1 1 0 000 2z"/>
                </svg>
                @if($cartCount > 0)
                    <span class="absolute -top-2 -right-2 w-4 h-4 bg-white text-brand-900 text-[10px] font-bold rounded-full flex items-center justify-center leading-none">
                        {{ $cartCount > 9 ? '9+' : $cartCount }}
                    </span>
                @endif
            </a>
            @endunless

            @auth
                @if(auth()->user()->role === \App\Enums\UserRole::Admin)
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-200 transition">Админ</a>
                @endif
                <a href="{{ auth()->user()->isAdmin() ? route('cabinet.settings') : route('cabinet.index') }}" class="hover:text-brand-200 transition">Личный кабинет</a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="hover:text-brand-200 transition">
                        Выйти
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="hover:text-brand-200 transition">Войти</a>
                <a href="{{ route('register') }}" class="px-4 py-1.5 border border-brand-300 rounded hover:bg-brand-800 transition text-sm">
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
                    @unless(auth()->user()?->isAdmin())
                    <a href="{{ route('cart.index') }}" class="py-2.5 border-b border-brand-800 hover:text-brand-200 transition flex items-center justify-between">
                        Корзина
                        @if($cartCount > 0)
                            <span class="px-1.5 py-0.5 bg-brand-700 text-white text-[10px] font-bold rounded-full leading-none">
                                {{ $cartCount > 9 ? '9+' : $cartCount }}
                            </span>
                        @endif
                    </a>
                    @endunless
                    @auth
                        @if(auth()->user()->role === \App\Enums\UserRole::Admin)
                            <a href="{{ route('admin.dashboard') }}" class="py-2.5 border-b border-brand-800 hover:text-brand-200 transition">
                                Админ
                            </a>
                        @endif
                        <a href="{{ auth()->user()->isAdmin() ? route('cabinet.settings') : route('cabinet.index') }}" class="py-2.5 border-b border-brand-800 hover:text-brand-200 transition">
                            Личный кабинет
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="py-2.5 text-left hover:text-brand-200 transition w-full">
                                Выйти
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="py-2.5 border-b border-brand-800 hover:text-brand-200 transition">
                            Войти
                        </a>
                        <a href="{{ route('register') }}" class="py-2.5 hover:text-brand-200 transition">
                            Регистрация
                        </a>
                    @endauth
                </nav>
            </div>
        </div>

    </div>
</header>
