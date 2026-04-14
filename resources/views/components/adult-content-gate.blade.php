@php
    $serverVerified = auth()->user()?->is_adult_verified ?? false;
    $postUrl = route('age-verification.store');
@endphp

<div
    x-data="{
        blocked: false,
        serverVerified: @js($serverVerified),
        postUrl: @js($postUrl),
        init() {
            if (this.serverVerified) {
                this.blocked = false;
                return;
            }
            if (localStorage.getItem('adult_consent') === 'accepted') {
                this.blocked = false;
                return;
            }
            this.blocked = true;
        },
        accept() {
            localStorage.setItem('adult_consent', 'accepted');
            fetch(this.postUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content'),
                    'Accept': 'application/json',
                },
            });
            this.blocked = false;
        },
        decline() {
            window.history.back();
        },
    }"
    x-show="blocked"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="adult-gate-heading"
>
    <div class="w-full max-w-md bg-surface rounded-xl shadow-2xl border border-border-subtle p-8 flex flex-col gap-5">

        <div class="text-center">
            <span class="inline-block mb-3 px-3 py-1 text-sm font-bold text-white bg-error rounded-full font-sans">
                18+
            </span>
            <h2 id="adult-gate-heading" class="font-serif text-2xl text-text-primary leading-snug">
                Контент для взрослых
            </h2>
        </div>

        <p class="text-sm text-text-muted leading-relaxed text-center font-sans">
            Эта книга содержит материалы для взрослых. Пожалуйста, подтвердите свой возраст.
        </p>

        <div class="flex flex-col sm:flex-row gap-3 mt-2">
            <button
                type="button"
                @click="accept()"
                class="flex-1 px-5 py-2.5 text-sm font-semibold text-white bg-brand-700 hover:bg-brand-800 rounded-lg transition-colors font-sans"
            >
                Мне есть 18 лет
            </button>
            <button
                type="button"
                @click="decline()"
                class="flex-1 px-5 py-2.5 text-sm font-semibold text-text-muted border border-border-subtle hover:bg-surface-muted rounded-lg transition-colors font-sans"
            >
                Назад
            </button>
        </div>

    </div>
</div>
