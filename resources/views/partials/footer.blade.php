<footer class="bg-brand-950 text-brand-200 mt-16">
    <div class="max-w-5xl mx-auto px-4 py-10">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">

            {{-- Static pages --}}
            <div>
                <h3 class="text-xs font-sans font-semibold uppercase tracking-widest text-brand-400 mb-3">
                    Информация
                </h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('static.about') }}" class="hover:text-white transition">О нас</a></li>
                    <li><a href="{{ route('static.contacts') }}" class="hover:text-white transition">Контакты</a></li>
                    <li><a href="{{ route('static.payment-info') }}" class="hover:text-white transition">Оплата и доставка</a></li>
                    <li><a href="{{ route('static.refund') }}" class="hover:text-white transition">Политика возврата</a></li>
                </ul>
            </div>

            {{-- Legal + social --}}
            <div>
                <h3 class="text-xs font-sans font-semibold uppercase tracking-widest text-brand-400 mb-3">
                    Правовые документы
                </h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('static.privacy') }}" class="hover:text-white transition">Политика конфиденциальности</a></li>
                    <li><a href="{{ route('static.terms') }}" class="hover:text-white transition">Пользовательское соглашение</a></li>
                    <li><a href="{{ route('static.offer') }}" class="hover:text-white transition">Публичная оферта</a></li>
                    <li><a href="{{ route('static.personal-data') }}" class="hover:text-white transition">Обработка персональных данных</a></li>
                    <li><a href="{{ route('static.cookies') }}" class="hover:text-white transition">Политика cookies</a></li>
                    <li><a href="{{ route('static.newsletter-consent') }}" class="hover:text-white transition">Согласие на рассылку</a></li>
                </ul>
            </div>

        </div>

        {{-- Newsletter subscribe --}}
        <div class="border-t border-brand-800 pt-8 pb-8">
            <div class="max-w-md">
                <h3 class="text-sm font-sans font-semibold text-white mb-1">Подпишитесь на рассылку</h3>
                <p class="text-xs text-brand-400 mb-3">Новинки, акции и рекомендации — только самое важное.</p>

                <form
                    method="POST"
                    action="{{ route('newsletter.subscribe') }}"
                    x-data="{ loading: false }"
                    @submit="loading = true"
                    class="flex gap-2"
                >
                    @csrf
                    <input
                        type="email"
                        name="email"
                        required
                        placeholder="Ваш email"
                        class="flex-1 px-3.5 py-2 rounded-lg bg-brand-900 border border-brand-700 text-sm text-white placeholder:text-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                    >
                    <button
                        type="submit"
                        :disabled="loading"
                        class="px-4 py-2 bg-brand-600 hover:bg-brand-500 text-white text-sm font-medium rounded-lg transition disabled:opacity-60 shrink-0"
                    >
                        <span x-show="!loading">Подписаться</span>
                        <span x-show="loading" x-cloak>...</span>
                    </button>
                </form>
                @if(session('newsletter_success'))
                    <p class="mt-1.5 text-xs text-green-400">{{ session('newsletter_success') }}</p>
                @endif
                @if($errors->has('email'))
                    <p class="mt-1.5 text-xs text-red-300">{{ $errors->first('email') }}</p>
                @endif
            </div>
        </div>

        {{-- Social links --}}
        <div class="border-t border-brand-800 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4">

            <div class="flex items-center gap-5">
                {{-- VK --}}
                <a href="#" aria-label="ВКонтакте" class="flex items-center gap-1.5 text-sm hover:text-white transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15.684 0H8.316C1.592 0 0 1.592 0 8.316v7.368C0 22.408 1.592 24 8.316 24h7.368C22.408 24 24 22.408 24 15.684V8.316C24 1.592 22.408 0 15.684 0zm3.692 17.123h-1.744c-.66 0-.862-.525-2.049-1.714-1.033-1-1.49-1.135-1.744-1.135-.356 0-.458.102-.458.593v1.566c0 .423-.135.677-1.253.677-1.846 0-3.896-1.118-5.335-3.202C4.624 10.857 4.03 8.57 4.03 8.096c0-.254.102-.491.593-.491h1.744c.441 0 .61.203.78.677.863 2.49 2.303 4.675 2.896 4.675.22 0 .322-.102.322-.66V9.721c-.068-1.186-.695-1.287-.695-1.71 0-.204.17-.407.44-.407h2.744c.373 0 .508.203.508.643v3.473c0 .372.17.508.271.508.22 0 .407-.136.813-.542 1.253-1.406 2.151-3.574 2.151-3.574.119-.254.322-.491.763-.491h1.744c.525 0 .644.27.525.643-.22 1.017-2.354 4.031-2.354 4.031-.186.305-.254.44 0 .78.186.254.796.779 1.203 1.253.745.847 1.32 1.558 1.473 2.049.17.474-.085.711-.609.711z"/>
                    </svg>
                    <span>ВКонтакте</span>
                </a>

                {{-- Telegram --}}
                <a href="#" aria-label="Telegram" class="flex items-center gap-1.5 text-sm hover:text-white transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                    </svg>
                    <span>Telegram</span>
                </a>

                {{-- Instagram --}}
                <a href="#" aria-label="Instagram" class="flex items-center gap-1.5 text-sm hover:text-white transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                    <span>Instagram</span>
                </a>

                {{-- Facebook --}}
                <a href="#" aria-label="Facebook" class="flex items-center gap-1.5 text-sm hover:text-white transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    <span>Facebook</span>
                </a>
            </div>

            <p class="text-xs text-brand-400">
                &copy; {{ date('Y') }} Книжная лавка
            </p>
        </div>

    </div>
</footer>
