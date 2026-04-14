# Documentation Index — Bookshop

Этот файл — точка входа для всех агентов. Читай только нужные разделы, не читай файлы целиком без необходимости.

---

## Быстрая навигация по фазам

| Фаза | Что делает | Blueprint-раздел | Roadmap-раздел |
|------|-----------|-----------------|----------------|
| 1 | Foundation & CI | [Phase 1 — No schema](architecture-blueprint.md#phase-1--project-foundation--ci) | [Phase 1](development-roadmap.md#phase-1--project-foundation--ci) |
| 2 | Storefront & Static Pages | [Phase 2 — books table](architecture-blueprint.md#phase-2--storefront--static-pages) | [Phase 2](development-roadmap.md#phase-2--storefront--static-pages) |
| 3 | Auth & OAuth | [Phase 3 — users, oauth_providers](architecture-blueprint.md#phase-3--user-authentication--oauth) | [Phase 3](development-roadmap.md#phase-3--user-authentication--oauth) |
| 4 | Admin Books | [Phase 4 — no new tables](architecture-blueprint.md#phase-4--admin-panel--books) | [Phase 4](development-roadmap.md#phase-4--admin-panel--books) |
| 5 | Cart & Stripe | [Phase 5 — cart_items, orders, order_items, user_books](architecture-blueprint.md#phase-5--cart--payments-stripe) | [Phase 5](development-roadmap.md#phase-5--cart--payments-stripe) |
| 6 | epub Delivery | [Phase 6 — download_logs](architecture-blueprint.md#phase-6--digital-delivery-epub) | [Phase 6](development-roadmap.md#phase-6--digital-delivery-epub) |
| 7 | User Cabinet | [Phase 7 — no new tables](architecture-blueprint.md#phase-7--user-dashboard--library) | [Phase 7](development-roadmap.md#phase-7--user-dashboard--library) |
| 8 | Blog | [Phase 8 — posts](architecture-blueprint.md#phase-8--blog) | [Phase 8](development-roadmap.md#phase-8--blog) |
| 9 | SEO | [Phase 9 — no new tables](architecture-blueprint.md#phase-9--seo-layer) | [Phase 9](development-roadmap.md#phase-9--seo-layer) |
| 10 | Analytics | [Phase 10 — analytics_events](architecture-blueprint.md#phase-10--analytics--event-tracking) | [Phase 10](development-roadmap.md#phase-10--analytics--event-tracking) |
| 11 | Admin Blog & Static Pages | [Phase 11 — static_pages, newsletter_subscribers](architecture-blueprint.md#phase-11--admin-panel--blog--static-pages) | [Phase 11](development-roadmap.md#phase-11--admin-panel--blog--storefront) |
| 12 | Hardening | [Phase 12 — indexes only](architecture-blueprint.md#phase-12--hardening--monitoring) | [Phase 12](development-roadmap.md#phase-12--hardening--monitoring) |

---

## Где что искать

### Бизнес-правила (numbered rules)
→ [`architecture-blueprint.md` → раздел "Business Logic Rules"](architecture-blueprint.md#business-logic-rules-all-phases)

Все 75 правил сгруппированы по фазам. Читай только нужную фазу.

### UI и поведение страниц
→ [`app-specification.md` → раздел 3-4](app-specification.md#3-pages--routes)

### Схема БД (все таблицы)
→ [`architecture-blueprint.md` → раздел "Migration Order"](architecture-blueprint.md#migration-order)

Быстрая шпаргалка по всем 13 миграциям с именами таблиц.

### Docker и среда разработки
→ [`docker-architecture.md`](docker-architecture.md)

Читай целиком только при проблемах с Docker. Для обычной работы достаточно раздела 6 (Development Workflow).

### Технический стек и locked decisions
→ [`development-roadmap.md` → "Locked Decisions"](development-roadmap.md#locked-decisions-do-not-re-debate)

### Нерешённые вопросы (open items)
→ [`development-roadmap.md` → "Risks & Gaps"](development-roadmap.md#risks--gaps-resolved)

---

## Таблицы по фазам (шпаргалка)

```
Phase 2:  books
Phase 3:  users (modify), oauth_providers
Phase 5:  cart_items, orders, order_items, user_books
Phase 6:  download_logs
Phase 8:  posts
Phase 10: analytics_events
Phase 11: static_pages, newsletter_subscribers
Phase 12: indexes on orders, download_logs, analytics_events
```

---

## Бэклог и дальнейшие планы

→ [`backlog.md`](backlog.md) — все задачи после Phase 12: мелкие правки перед деплоем, технический долг, новые функции

---

## Memory агентов

| Файл | Что содержит |
|------|-------------|
| [`MEMORY.md`](MEMORY.md) | Индекс памяти архитектора |
| [`project_architecture.md`](project_architecture.md) | Ключевые архитектурные решения (для всех агентов) |
