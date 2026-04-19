# Backlog — Digital Bookshop

Список задач и улучшений после завершения Phase 12. Приоритет сверху вниз внутри каждой секции.

---

## Мелкие правки (перед деплоем)

Небольшие улучшения, которые не требуют архитектурных решений.

### Аудит тестов — убрать классы `Phase*`
Тесты написаны с именами вида `Phase5CartTest`, `Phase8BlogTest` и т.п. Переименовать в семантические имена (`CartTest`, `BlogTest` и т.д.), убрать prefix `Phase*` из всех тестовых классов и файлов.

### ~~Конфиг enabled для OAuth-провайдеров~~ ✅ done
Реализовано: `services.google.enabled` / `services.vk.enabled` в `config/services.php`, управляется через `GOOGLE_OAUTH_ENABLED` / `VK_OAUTH_ENABLED` в `.env`. VK по умолчанию `false`.

### Валюта вынесена в конфиг
Сейчас `RUB` и `₽` хардкодом разбросаны по ~25 местам: миграции (`default('RUB')`), сервисы (`OrderService`, `StripePaymentProvider`, `BookAdminService`), 14 Blade-шаблонов (символ `₽`), GA4-события и JSON-LD (`priceCurrency`), фабрики и тест-фикстуры. Вынести в `config/shop.php`:
```php
'currency_code'   => env('SHOP_CURRENCY', 'RUB'),   // ISO 4217
'currency_symbol' => env('SHOP_CURRENCY_SYMBOL', '₽'),
```
Заменить все хардкоды на `config('shop.currency_code')` / `config('shop.currency_symbol')`.

### Человекочитаемое имя файла при скачивании книги
Сейчас скачиваемый файл получает имя вида `a3f7c...uuid...epub` — пользователь вынужден переименовывать вручную перед переносом на Kindle или телефон. Установить `Content-Disposition: attachment; filename="{slug}.epub"` в контроллере скачивания. Слаг уже есть на модели `Book`, безопасен для файловых систем, не содержит спецсимволов. При реализации мультиформата — `{slug}.fb2` и т.д.

### ~~Конфиг enabled для платёжных систем~~ ✅ done
Реализовано: `services.stripe.enabled` / `services.paypal.enabled` в `config/services.php`, управляется через `STRIPE_ENABLED` / `PAYPAL_ENABLED` в `.env`. PayPal по умолчанию `false`. `PaymentProviderRegistry` проверяет флаг через lazy closure — не инстанциирует провайдер при `enabled = false`.

---

## Мультиформатные книги (EPUB, FB2 + конвертация из DOCX)

Сейчас книги хранятся строго как EPUB. Расширить до поддержки FB2, принимать DOCX как источник.

### Архитектура

**`FormatConverter` контракт** — абстракция над конкретным инструментом:
```php
interface FormatConverter
{
    public function convert(string $inputPath, string $outputFormat): ConversionResult;
    public function supports(string $inputFormat, string $outputFormat): bool;
}
```

`ConversionResult` — value object: `outputPath`, `success`, `errorMessage`.

**Реализации:**
- `CalibreConverter` — shell `ebook-convert`, универсальный (EPUB→FB2, DOCX→любой)
- `PandocConverter` — shell `pandoc`, лучшее качество для DOCX→EPUB

**`ConverterRegistry`** — подбирает нужный конвертер по паре `(sourceFormat, targetFormat)`. Джоб работает только с интерфейсом.

**Приоритет конверсий:**
- DOCX→EPUB: Pandoc (качество прозы лучше)
- DOCX→FB2: Calibre
- EPUB→FB2: Calibre

Форматы: **EPUB + FB2** (MOBI не нужен — deprecated Amazon).

### DB — таблица `book_files`

```
id
book_id          FK → books
format           enum: docx, epub, fb2
role             enum: source, derived
path             string (S3 path, nullable)
status           enum: pending, processing, ready, failed
error_message    text nullable
converted_at     timestamp nullable
timestamps
```

`role=source` — загруженный файл (docx или epub). `role=derived` — сгенерированные форматы.

Поле `epub_path` на таблице `books` остаётся как есть для обратной совместимости — при реализации решить: мигрировать в `book_files` или оставить дублирование.

### Флоу

1. Админ загружает DOCX или EPUB → файл в S3, запись `book_files` (role=source, status=ready)
2. `BookObserver` → dispatches `ConvertBookFormats` job (одна запись `pending` на каждый производный формат)
3. Job: `ConverterRegistry::for($sourceFormat, $targetFormat)` → конвертирует → обновляет статус
4. На ошибке: `status=failed`, `error_message` = stderr конвертера

### Admin UI

На странице редактирования книги — блок «Файлы»:

```
Источник:  book.docx   [ready]
EPUB:      —           [processing...]
FB2:       —           [failed: "ebook-convert exit 1: ..."] [Повторить]
```

Кнопка «Повторить» — re-dispatch job для конкретного формата.

### Скачивание (пользователь)

`/books/{book}/download?format=epub` — только `ready` форматы показываются в dropdown.

### Docker

- Pandoc добавить в `docker/php/Dockerfile` (~100MB)
- Calibre добавить в `docker/php/Dockerfile` (~500MB, CLI-only без GUI)

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
