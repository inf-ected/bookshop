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

### Конфиг enabled для платёжных систем
Добавить флаг `config('payments.providers.stripe.enabled')` (и аналогичные для будущих провайдеров). При `enabled = false` — скрывать кнопку оплаты и возвращать понятную ошибку. Позволит оперативно отключить провайдер без деплоя. Затронет: `config/payments.php` (новый файл), `CheckoutController`, Blade-шаблоны оплаты.

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
