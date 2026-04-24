# Backlog — Digital Bookshop

Список задач и улучшений после завершения Phase 12. Приоритет сверху вниз внутри каждой секции.

---

## Мелкие правки (перед деплоем)

Небольшие улучшения, которые не требуют архитектурных решений.

### Аудит тестов — убрать классы `Phase*` и навести структуру
Тесты написаны с именами вида `Phase5CartTest`, `Phase8BlogTest` и т.п. Переименовать в семантические имена (`CartTest`, `BlogTest` и т.д.), убрать prefix `Phase*` из всех тестовых классов и файлов.

Дополнительно навести структуру в тестах оплаты. Сейчас `CheckoutControllerTest` де-факто тестирует только Stripe (мок провайдера завязан на Stripe-специфику), вебхуки разнесены несистемно — `StripeWebhookTest` отдельно, вебхуки PayPal внутри `PayPalPaymentProviderTest`. Целевая структура:
- `CheckoutControllerTest` — контроллер с provider-agnostic моком (не привязан к конкретному провайдеру)
- `StripePaymentProviderTest` — логика Stripe провайдера
- `StripeWebhookTest` — вебхуки Stripe
- `PayPalPaymentProviderTest` — логика PayPal провайдера
- `PayPalWebhookTest` — вебхуки PayPal

### ~~Конфиг enabled для OAuth-провайдеров~~ ✅ done
Реализовано: `services.google.enabled` / `services.vk.enabled` в `config/services.php`, управляется через `GOOGLE_OAUTH_ENABLED` / `VK_OAUTH_ENABLED` в `.env`. VK по умолчанию `false`.

### ~~Валюта вынесена в конфиг~~ ✅ done
Реализовано: `config/shop.php` содержит `currency_code`, `currency_symbol`, `currency_decimals`, `currency_decimal_sep` — управляется через `SHOP_CURRENCY` / `SHOP_CURRENCY_SYMBOL` в `.env`. Все шаблоны используют `config('shop.currency_*')`. Дефолты `RUB` остались только в миграциях (несущественно — значение при создании записи, не используется в бизнес-логике).

### ~~Человекочитаемое имя файла при скачивании книги~~ ✅ done
Реализовано: `ResponseContentDisposition` передаётся в `temporaryUrl()` в `DownloadService::generateUrl()`. Браузер получает `{slug}.epub` вместо UUID-пути S3. При мультиформате — заменить расширение на основе формата.

### ~~Конфиг enabled для платёжных систем + PayPal провайдер~~ ✅ done
Реализовано: `PaymentGateway` enum, `PaymentProvider` / `SupportsWebhooks` контракты, `PaymentProviderRegistry` с lazy factory closures. Stripe и PayPal как полноценные провайдеры. Управляется через `STRIPE_ENABLED` / `PAYPAL_ENABLED` в `.env`. PayPal по умолчанию `false`.

---

## ~~Мультиформатные книги (EPUB, FB2 + конвертация из DOCX)~~ ✅ done (PR #60)

Реализовано в Phase 13. EPUB + FB2 как клиентские форматы, DOCX принимается как источник конвертации и никогда не выдаётся пользователям. Конвертация: Pandoc (DOCX→EPUB) + Calibre (остальные пары). Admin UI с live-polling статусов. Per-format download кнопки в библиотеке и на странице книги. `epub_path` удалён из таблицы `books`, заменён на `book_files`. Подробности: `docs/blueprint-multiformat-books.md`.

---

## Технический долг

### Checkout order reuse
**Проблема:** каждый чекаут создаёт новый `Order`. После введения `OrderTransaction` это семантически неверно — мусорные ордера при каждой незавершённой попытке оплаты.

**Желаемый флоу:**
- `Order` = намерение купить (живёт до оплаты или явной отмены)
- `OrderTransaction` = попытка оплаты через провайдера
- Повторный чекаут → тот же `Order`, новая `OrderTransaction`
- Сессия истекла → `OrderTransaction.status = expired`, `Order` остаётся `pending`

Затронет: `CheckoutController::store()`, `CartService`, `ExpirePendingOrdersCommand`, тесты.

### Redis cache (BookObserver, PostObserver)
Кэшировать результаты `CatalogService::listPublished()` и `CatalogService::listFeatured()` через Redis. Инвалидировать через Observer при изменении книги/поста. Откладывалось до пост-деплой аудита нагрузки.

### Cover image optimization
Job `OptimizeCoverImage` — конвертация загруженных обложек в WebP, генерация нескольких размеров. Актуально когда каталог вырастет. Команда `php artisan app:optimize-covers` для ретроспективной обработки.

---

## Каталог и контент

### Серии книг
Книги могут входить в серию (например, трилогия). Таблица `series` (id, title, slug, sort_order). Связь `books.series_id` (nullable). На странице книги — блок «Другие книги серии». В каталоге — фильтр по серии.

### Категории / жанры / теги
Единая система тегов через полиморфные `tags` + `taggables`. Три семантических типа тегов: `genre` (жанр), `category` (раздел), `tag` (произвольный). Фильтрация в каталоге. Облако тегов. Затронет: новые таблицы `tags`, `taggables`, admin-формы книг, страницу каталога.

---

## Социальные функции

### Оценки читателей
Пользователи, купившие книгу, могут поставить оценку (1–5 звёзд). Таблица `book_ratings` (user_id, book_id, rating, created_at). Агрегированный рейтинг на карточке и странице книги. Только для владельцев книги (`user_books`).

### Рецензии / комментарии читателей
Пользователи, купившие книгу, могут оставить текстовую рецензию. Таблица `book_reviews` (user_id, book_id, body, is_approved). Модерация в админке. Вывод на странице книги. Только для владельцев. Можно объединить с оценками (rating + review).

---

## Маркетинг

### Промокоды и скидки
Таблица `promo_codes` (code, discount_type: percent|fixed, discount_value, usage_limit, used_count, expires_at, is_active). Применение на этапе чекаута. Скидка применяется к сумме заказа. Ввод промокода на странице корзины или чекаута. Валидация в `CheckoutController`.

---

## Деплой

→ Полный план: [`docs/deployment-plan.md`](deployment-plan.md)
