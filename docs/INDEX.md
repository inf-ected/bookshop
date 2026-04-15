# Documentation Index — Bookshop

Этот файл — точка входа для всех агентов. Читай только нужные разделы, не читай файлы целиком без необходимости.

---

## Статус фаз — все завершены ✅

| Фаза | Что делает | Статус |
|------|-----------|--------|
| 1 | Foundation & CI | ✅ merged PR #1 |
| 2 | Storefront & Static Pages | ✅ merged |
| 3 | Auth & OAuth | ✅ merged PRs #4–9 |
| 4 | Admin Books | ✅ merged |
| 5 | Cart & Stripe | ✅ merged PRs #12–15 |
| 6 | epub Delivery | ✅ merged PR #16 |
| 7 | User Cabinet | ✅ merged PRs #18–20 |
| 8 | Blog | ✅ merged PRs #22–24 |
| 9 | SEO | ✅ merged PRs #26–28 |
| 10 | Analytics (GA4) | ✅ merged PR #30 |
| 11 | Admin Panel — Blog & Storefront | ✅ merged PR #31 |
| 12 | Hardening & Monitoring | ✅ merged PRs #34–39 |
| **13** | **Deployment** | **→ [deployment-plan.md](deployment-plan.md)** |

> Blueprint (phases 1–12) завершён. Агент-воркфлоу (architect → backend → frontend → reviewer) больше не нужен для плановых фаз. Новые задачи — через backlog.md.

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

## Деплой

→ [`deployment-plan.md`](deployment-plan.md) — пошаговый план: домен, Cloudflare, Hetzner VPS, S3, Resend, Stripe, GA4, CI/CD, smoke test

## Бэклог и дальнейшие планы

→ [`backlog.md`](backlog.md) — задачи после деплоя: технический долг, новые функции, улучшения

---

## Memory агентов

| Файл | Что содержит |
|------|-------------|
| [`MEMORY.md`](MEMORY.md) | Индекс памяти архитектора |
| [`project_architecture.md`](project_architecture.md) | Ключевые архитектурные решения (для всех агентов) |
