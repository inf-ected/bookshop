@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto px-4 py-12">

    <h1 class="font-serif text-3xl md:text-4xl text-text-primary mb-8">Контакты</h1>

    <div class="font-sans text-base leading-relaxed text-text-primary space-y-5">

        <p>
            Если у вас возникли вопросы по работе сервиса, оплате, доступу к купленным книгам
            или любые другие — мы готовы помочь. Свяжитесь с нами удобным способом.
        </p>

        <div class="bg-surface-muted rounded-lg p-6 mt-6 space-y-4">

            <div class="flex items-start gap-3">
                <span class="text-text-muted mt-0.5">✉</span>
                <div>
                    <p class="font-semibold text-text-primary">Электронная почта</p>
                    <a href="mailto:support@example.com" class="text-brand-700 hover:text-brand-900 transition">
                        support@example.com
                    </a>
                    <p class="text-sm text-text-muted mt-1">Мы отвечаем в течение 24 часов в рабочие дни.</p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <span class="text-text-muted mt-0.5">⏰</span>
                <div>
                    <p class="font-semibold text-text-primary">Время работы поддержки</p>
                    <p class="text-text-muted">Понедельник — пятница, 10:00 — 19:00 по московскому времени.</p>
                </div>
            </div>

        </div>

        <p class="text-sm text-text-muted mt-4">
            Для вопросов, связанных с возвратом средств, пожалуйста, указывайте в письме
            номер заказа — это поможет нам обработать ваш запрос быстрее.
        </p>

    </div>

</div>

@endsection
