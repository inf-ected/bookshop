---
name: Bookshop Architecture Decisions
description: Core architectural decisions for the 12-phase Russian digital bookshop - entities, enums, patterns, conventions
type: project
---

Laravel 12 / PHP 8.4 Russian-language digital bookshop (3-5 books, epub only, single author).

**Why:** Single-author small-scale bookshop with Stripe payments, OAuth, and digital delivery.
**How to apply:** All designs should favor simplicity given the tiny catalog size. No pagination needed for books. No complex search.

Key decisions:
- Money: unsignedInteger kopecks, currency char(3) default RUB
- PKs: auto-increment bigint (standard Laravel id())
- Enums: PHP string-backed enums cast at model level, stored as string columns
- Auth: role enum on users (user/admin), EnsureAdmin middleware returns 404
- User implements MustVerifyEmail
- OAuth: Google, VK, Instagram, Facebook via Socialite; multi-provider linking; oauth_providers table
- Cart: cart_items table with nullable user_id/session_id for guest+auth support
- Payments: Stripe Checkout, webhook-first (order stays pending until webhook confirms)
- File delivery: controller-proxied pre-signed S3 URL
- Ownership: user_books pivot table
- S3: two disks (s3-public for covers, s3-private for epubs)
- Blog: posts table, same status enum pattern as books
- Static pages: DB-managed from Phase 11, seeded from Blade templates
- Cache: Redis with model observer invalidation
- No soft deletes anywhere

Core entities: users, oauth_providers, books, cart_items, orders, order_items, user_books, download_logs, posts, analytics_events, static_pages
