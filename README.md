# Digital Bookshop

A production-ready e-commerce platform for selling digital books (ePub), built with Laravel 12 and PHP 8.4. Designed as a reusable storefront template — everything is configurable for any single-author or small-publisher book shop.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Frontend | Blade, Alpine.js, Tailwind CSS v4 |
| Database | MySQL 8 |
| Cache / Session / Queue | Redis |
| File Storage | S3-compatible (MinIO in development) |
| Payments | Stripe (abstracted behind `PaymentProvider` interface) |
| Email | Resend (via `resend/resend-laravel`) |
| Queue Monitoring | Laravel Horizon |
| Debug / Profiling | Laravel Telescope |
| Error Tracking | Sentry |
| Backups | `spatie/laravel-backup` (S3 + email notification) |
| Containerisation | Docker (nginx + PHP-FPM + MySQL + Redis + MinIO) |
| Static Analysis | PHPStan level 5 + Larastan + banned-code extension |
| Code Style | Laravel Pint |
| Tests | PHPUnit 11 — 291 tests, 642 assertions |

---

## Features

### Storefront
- Book catalog with cover images, annotations, and sample fragment pages
- Featured books carousel on the homepage
- `is_available` flag — hides a book from the catalog without deleting it
- Adult content gate (18+ overlay) — server-side guard + Alpine.js overlay + no-JS fallback; consent persisted in `localStorage` for guests and in DB for authenticated users
- SEO: per-page `<meta>` tags, OpenGraph, Twitter Cards, JSON-LD structured data, auto-generated `sitemap.xml` and `robots.txt`

### Authentication
- Email + password registration with email verification
- OAuth via Google, VK, Facebook, Instagram (Socialite + community providers)
- Incomplete OAuth profiles (provider returns no email) handled with an inline completion form — no separate route
- Password reset via email

### Cart & Checkout
- Guest cart persisted in session; merged into user cart on login (duplicates discarded)
- Users cannot add books they already own (active ownership check)
- Users with a revoked book can re-add it to the cart and re-purchase
- Order created before Stripe redirect; webhook is the source of truth for payment confirmation
- Stripe Checkout Session flow with webhook handler (`checkout.session.completed`, `checkout.session.expired`)
- Idempotent payment processing — row-level lock prevents double-processing concurrent webhooks
- Polling endpoint (`/checkout/status/{order}`) for the success page while the webhook is in-flight
- Payment provider abstracted behind `PaymentProvider` and `SupportsWebhooks` contracts — adding a second provider (e.g. PayPal) requires a new class, no changes to controllers

### Digital Delivery
- ePub files stored on a private S3 bucket
- Downloads served via pre-signed S3 URLs with configurable TTL
- `BookPolicy` enforces ownership before generating a URL
- Download events logged to `download_logs` with IP and User-Agent

### User Cabinet
- Personal library showing owned books (revoked books excluded)
- Order history with itemised view
- Account settings: name, email, password, newsletter consent
- OAuth provider linking / unlinking from settings page

### Blog
- Full blog with publish/draft status
- Post body stored as server-side sanitised HTML (`ezyang/htmlpurifier`)
- Optional cover images

### Newsletter
- Subscription with consent flag on User model
- Admin broadcast: compose and send to all subscribers via Resend
- Subscriber management in admin panel

### Admin Panel
- Book management: create, edit, delete, toggle published / draft / available / featured
- Order list and detail view; refund link to Stripe Dashboard
- User management: view, ban/unban, verify email, force password reset
- Book access management: manually grant a book, revoke access, restore revoked access
  - Re-granting a previously revoked book restores the existing row (no duplicates)
- Blog post management: create, edit, delete, toggle published/draft
- Newsletter broadcast composer
- Download log viewer

### Security & Infrastructure
- Content Security Policy (CSP) via middleware — strict in production, relaxed for Alpine.js `unsafe-eval` in local
- Rate limiting on login, registration, password reset, and checkout endpoints
- HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy headers
- Cookie consent banner (GDPR) — consent persisted in `localStorage`; Google Analytics fires only after consent
- Performance indexes on high-traffic query paths (`books`, `orders`, `user_books`, `cart_items`)
- Automated daily backups to S3 with email notification on failure
- Horizon for queue monitoring (`payments` queue + default queue)
- Telescope for request / query / job / mail inspection in local and staging
- Scheduled commands: cart cleanup, pending order expiration

---

## Architecture

### Feature-based Directory Structure

All application code is organised into feature slices under `app/Features/`. Each feature owns its controllers, services, form requests, jobs, events, listeners, and mail classes:

```
app/Features/
├── Admin/       — book/order/user management
├── Auth/        — login, registration, OAuth, password
├── Blog/        — posts
├── Cabinet/     — user profile, library, orders
├── Cart/        — guest and authenticated cart
├── Catalog/     — public storefront pages
├── Checkout/    — orders, Stripe, webhooks, confirmation
├── Download/    — ePub delivery via S3 pre-signed URLs
├── Newsletter/  — subscription and broadcast
└── Pages/       — static pages, sitemap, robots
```

Shared code (Models, Policies, Enums, Providers) lives in `app/` root.

### Payment Abstraction

The checkout layer talks to a `PaymentProvider` interface:

```php
interface PaymentProvider {
    public function getName(): string;
    public function createSession(Order $order, User $user): array;
    public function extractReturnSessionId(Request $request): ?string;
    public function handleReturn(Request $request, Order $order): void;
}
```

`WebhookController` depends on a separate `SupportsWebhooks` interface, so a provider that does not use webhooks does not need to implement it. Concrete bindings are registered via contextual binding in `AppServiceProvider` — the controllers never reference `StripePaymentProvider` directly.

### Revoke / Re-purchase Flow

`user_books` records are never deleted. Revocation sets `revoked_at`; restoration clears it:

- Library queries filter `whereNull('revoked_at')` — revoked books are invisible to the user
- Cart blocks re-adding only if the user has an **active** (non-revoked) ownership record
- On payment confirmation (`ProcessPaymentConfirmation` job), if a revoked row exists for the purchased book it is restored in-place (`revoked_at = null`) rather than creating a duplicate
- Admin grant follows the same pattern — re-granting a revoked book restores the row

### Key Design Decisions

| Decision | Rationale |
|---|---|
| Order created before Stripe redirect | Prevents orphaned payments with no corresponding order |
| Webhook is source of truth for payment | The success redirect is unreliable — users can close the tab; webhooks fire regardless |
| `order_transactions` table separate from `orders` | Supports multiple payment attempts per order and multiple providers without schema changes |
| `ProcessPaymentConfirmation` job with `lockForUpdate` | Concurrent Stripe retries cannot both process the same order |
| Private S3 bucket for ePubs + pre-signed URLs | Files are never publicly accessible; URL expiry limits link sharing |
| HTMLPurifier for blog body | Server-side sanitisation prevents stored XSS even if the admin panel is compromised |
| Feature-slice architecture | Each domain is independently navigable; no cross-feature service imports |

---

## Local Development

### Prerequisites

Docker + Docker Compose

### Setup

```bash
cp .env.example .env
# fill in STRIPE_*, RESEND_KEY, AWS_*, GOOGLE_CLIENT_* in .env
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec php php artisan migrate --seed
```

| URL | Service |
|---|---|
| http://localhost:8080 | App (HTTP) |
| https://localhost:8443 | App (HTTPS) |
| http://localhost:9001 | MinIO console |
| http://localhost:8080/horizon | Horizon |
| http://localhost:8080/telescope | Telescope |

The `node` container runs `npm run dev` (Vite HMR) automatically — no Node.js on the host required.

### Without Docker

```bash
composer run setup   # install deps, .env, key, migrate, build assets
composer run dev     # server + queue + logs + vite
```

### Useful Commands

```bash
php artisan test                       # run all tests
php artisan test --filter=CartTest     # run a single suite
./vendor/bin/pint                      # fix code style
composer analyse                       # PHPStan static analysis
```

---

## Environment Variables

| Variable | Purpose |
|---|---|
| `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | Stripe integration |
| `RESEND_KEY` | Transactional email |
| `AWS_*` + `AWS_ENDPOINT` | S3-compatible storage (MinIO in dev) |
| `BACKUP_DISK` / `BACKUP_NOTIFICATION_EMAIL` | Automated backup destination |
| `SENTRY_LARAVEL_DSN` | Error tracking |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | OAuth (VK, Facebook, Instagram follow the same pattern) |

---

## Testing

Tests use SQLite in-memory — no Docker DB needed:

```bash
php artisan test
# Tests: 291 passed (642 assertions)
```

Coverage includes: auth flows, OAuth, cart logic (guest/user/merge/revoke), checkout controller, Stripe webhook handling, payment confirmation job (idempotency, revoke/re-purchase restoration), admin panel (books, orders, users, grants), download policy, blog, cabinet, sitemap, CSP headers, rate limiting, age verification, backups.
