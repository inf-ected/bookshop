# Development Roadmap — Digital Bookshop

**Project**: Single-author digital book store
**Stack**: Laravel 12, PHP 8.4, MySQL, Redis, Docker, S3
**Language**: Russian only (no i18n)
**Last updated**: 2026-03-18

---

## How to use this document

Each phase is a self-contained feature unit:

1. Pick the next phase
2. Send it to the architect → get DB/API design
3. Implement
4. Commit and deploy
5. Repeat

Every phase must leave the application in a deployable state.

---

## Locked Decisions (do not re-debate)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Language | Russian only | Single author, Russian-language books |
| Scale | 3–5 books max | Author does not plan more |
| Frontend | Blade + Alpine.js + Tailwind | Mobile-first, minimalist |
| S3 | AWS prod / MinIO (Docker) dev | S3-compatible, best local dev tooling |
| File format | epub only | Single format for now |
| Cart model | Cart + CartItem (multi-book checkout) | Not single-click per book |
| Free books | None — not planned | No bypass flow needed |
| File delivery | Controller-proxied signed URL: `GET /books/{book}/download` | Not direct S3 URL |
| Ownership | Separate `user_book` pivot table | Independent of Order; supports future grants |
| Admin role | `role` enum (`user`, `admin`) on users table | Extensible |
| Book status | `draft` / `published` enum (no soft deletes) | Purchasers always get access via `user_book` |
| Book file versioning | Latest file version always served | No per-version access tracking |
| Webhook idempotency | Webhook-first: order stays `pending` until webhook confirms | Prevents race with redirect |
| OAuth | Google, VK, Instagram, Facebook; email always required | Email mandatory for transactional mail |
| Multi-provider | One account can link multiple OAuth providers | Add/remove from profile settings |

---

## Phases Overview

| # | Phase | Milestone |
|---|-------|-----------|
| 1 | Project Foundation & CI | MVP |
| 2 | Storefront & Static Pages | MVP |
| 3 | User Authentication & OAuth | MVP |
| 4 | Admin Panel — Books | MVP |
| 5 | Cart & Payments (Stripe) | MVP |
| 6 | Digital Delivery (epub) | Beta |
| 7 | User Dashboard & Library | Beta |
| 8 | Blog / News | Beta |
| 9 | SEO Layer | Beta |
| 10 | Analytics & Event Tracking | Beta |
| 11 | Admin Panel — Blog & Storefront | Production-ready |
| 12 | Hardening & Monitoring | Production-ready |

---

## Phase 1 — Project Foundation & CI

**Goal**: Coding standards, testing baseline, deployment pipeline. Every future phase builds on this.

### Features
- `.env` structure and config casts finalized
- Laravel Pint enforced in CI
- PHPUnit baseline with SQLite in-memory
- CI pipeline: lint → test → build (GitHub Actions or equivalent)
- Error tracking (Sentry)
- Health-check route `/up`
- MinIO service added to Docker Compose for local S3 dev

### Key Technical Decisions
- Blade + Alpine.js + Tailwind confirmed as frontend stack
- Mobile-first breakpoint strategy defined in Tailwind config
- Minimalist design system: color palette, typography scale, spacing tokens — defined once

### Dependencies
- Docker setup (done)
- Laravel installed (done)

### Git
- **Branch**: `feature/project-foundation`
- **Commit**: `chore: set up CI, Pint, Sentry, MinIO and health check`

---

## Phase 2 — Storefront & Static Pages

**Goal**: Public-facing homepage with book carousel, navigation, footer, and all required legal/info static pages. No auth, no purchase.

### Features

**Homepage**
- Horizontal book carousel (3–5 cards)
- Book card: cover image, title, short preview (optional), "Add to cart" button (inactive — cart comes in Phase 5)
- Burger menu: links to catalog, login, register, personal cabinet
- Footer: social media links (VK, Instagram, Facebook, Telegram), links to static pages

**Book Catalog** (`/books`)
- Grid of all published books (same card component as carousel)
- No pagination needed (≤5 books)

**Book Detail Page** (`/books/{slug}`)
- Title
- Cover image
- "Add to cart" / "Buy" button (inactive for now)
- Short annotation (independent field from excerpt)
- Excerpt/preview text (independent field, entered manually in admin — Phase 4)
- Link to sample fragment page

**Sample Fragment Page** (`/books/{slug}/fragment`)
- Read-only fragment display — no text selection/copy (CSS + JS guard)
- Pagination if fragment is long (configurable pages or characters per page)
- On last page: "Конец ознакомительного фрагмента" end-cap with CTA to buy
- No auth required (publicly accessible)

**Static Pages** (Blade views, content managed via config or simple DB table — decide in Phase 11)
- `/about` — О нас
- `/privacy` — Политика конфиденциальности
- `/terms` — Пользовательское соглашение
- `/offer` — Публичная оферта
- `/personal-data` — Политика обработки персональных данных
- `/newsletter-consent` — Согласие на получение рассылки
- `/cookies` — Политика использования cookies *(recommended — required by 152-ФЗ)*
- `/refund` — Политика возврата *(recommended — digital goods refund policy)*
- `/contacts` — Контакты *(recommended — support email, response time)*
- `/payment-info` — Оплата и доставка *(recommended — how payment works, epub delivery explained)*

### Key Technical Decisions
- Book card is a reusable Blade component (`<x-book-card :book="$book" />`)
- Carousel: CSS scroll-snap + Alpine.js swipe — no heavy carousel library
- Mobile-first: all layouts designed at 375px first, then expanded
- Static page content: hardcoded in Blade for now (editable via admin in Phase 11)
- Slug: auto-generated from title via `Str::slug()`, editable in admin

### Dependencies
- Phase 1 complete

### Git
- **Branch**: `feature/storefront`
- **Commit**: `feat: add homepage carousel, book catalog, book detail, sample fragment and static pages`

---

## Phase 3 — User Authentication & OAuth

**Goal**: Users can register, log in, and log out. OAuth with mandatory email. Multiple providers per account.

### Features
- Registration: name, email (mandatory), password
- Login / logout
- Password reset via email
- Email verification (mandatory before checkout)
- OAuth login: Google, VK, Instagram, Facebook
  - If provider does not return email → prompt user to enter email (blocking step before account creation)
  - On first OAuth login: create account, link provider
  - On subsequent OAuth login with existing email: link to existing account
- Profile settings: connect / disconnect additional OAuth providers
- Protected route middleware (`auth`, `verified`)
- Empty user dashboard shell (`/cabinet`) — placeholder for Phase 7

### Key Technical Decisions
- Laravel Breeze (Blade stack) as auth scaffold — minimal, stylable
- Socialite for all OAuth providers; VK/Instagram/Facebook via community Socialite providers
- `oauth_providers` table: user_id, provider, provider_id, token, refresh_token
- Email is always required and verified — no exceptions
- Do NOT use Jetstream

### Dependencies
- Phase 2 (navbar/burger menu links to login/register)

### Git
- **Branch**: `feature/auth`
- **Commit**: `feat: add auth with OAuth (Google, VK, Instagram, Facebook), email verification and provider linking`

---

## Phase 4 — Admin Panel — Books

**Goal**: Author manages the entire book catalog from a UI. No DB access needed.

### Features

**Admin Gate**
- `role` enum on users: `user` / `admin`
- Admin middleware on all `/admin/*` routes
- Redirect unauthorized to 404 (not 403 — do not expose admin existence)

**Book CRUD**
- Create book → defaults to `draft` status
- Edit: title, slug, cover image, short annotation, excerpt text, sample fragment text, price (in kopecks/cents)
- Upload epub file → stored on private S3 bucket
- Set status: `draft` → `published` (and back)
- Set `featured` flag (controls carousel inclusion)
- Set `sort_order` (controls carousel/catalog order)
- Delete (only `draft` books; published books with purchases cannot be deleted)

**Book Status Flow**
```
draft → published → (can unpublish back to draft if no purchases)
```

**File Management**
- epub upload → queued S3 upload job (avoids timeout)
- On new epub upload → replaces previous file; all existing purchasers automatically get latest version via `user_book` pivot (which points to book, not to a specific file version)

**Admin Book List**
- Shows all books (draft + published) with status badge
- Quick toggle: publish/unpublish, featured on/off

### Key Technical Decisions
- Custom Blade admin — no Filament/Nova
- Sample fragment: plain `<textarea>` (large TEXT column) — author pastes text manually
- epub stored at `books/{book_id}/book.epub` — simple, single-file per book
- Cover images stored on public S3 bucket: `covers/{book_id}/cover.{ext}`
- File upload via queued job: `ProcessBookFileUpload`
- Price stored as integer (kopecks) — display layer handles formatting

### Dependencies
- Phase 3 (admin is a user with `role = admin`)

### Git
- **Branch**: `feature/admin-books`
- **Commit**: `feat: add admin panel with book CRUD, draft/publish flow and epub upload`

---

## Phase 5 — Cart & Payments (Stripe)

**Goal**: Users can add books to cart and pay. Stripe Checkout. Webhook-first order confirmation.

### Features

**Cart**
- `cart_items` table: user_id (nullable for guest), session_id, book_id
- Add to cart from book card / book detail page
- Cart page: list of selected books, cover, title, price, remove button, total
- Guest cart: session-based; on login → merge with user cart
- Prevent adding already-purchased books to cart (check `user_book`)
- Prevent duplicate cart items

**Checkout**
- If not logged in: prompt to log in or register (redirect back to cart after auth)
- Review order → "Pay" button
- Create Stripe Checkout Session (one session per cart, line items = cart books)
- Redirect to Stripe hosted page
- Success URL: `/checkout/success?session_id={CHECKOUT_SESSION_ID}`
- Cancel URL: `/cart` (cart is preserved)

**Order & Webhook**
- On Stripe session creation: create `Order` with `status = pending`, create `OrderItem` per book
- Webhook endpoint: `POST /webhooks/stripe` (signed, verified)
- On `checkout.session.completed`: mark order `paid`, create `user_book` records
- Idempotency: check `orders.stripe_session_id` before processing — skip if already paid
- On success redirect: if order already `paid` (webhook was faster) → go directly to cabinet; if still `pending` → wait/poll briefly, then show "processing" page
- Order confirmation email: queued after webhook confirms payment

**PaymentProvider abstraction**
- `PaymentProvider` interface: `createSession(Cart, User): string` (returns redirect URL), `handleWebhook(Request): void`
- `StripePaymentProvider` implements it
- Bound in `AppServiceProvider` — swap provider by changing binding

**Order model fields**: `id`, `user_id`, `status` (pending/paid/refunded/failed), `total_amount` (integer, kopecks), `currency` (char 3, default RUB), `payment_provider`, `stripe_session_id`, `stripe_payment_intent_id`, `paid_at`

### Key Technical Decisions
- Stripe Checkout (hosted page) — PCI scope offloaded entirely
- No free books → no bypass flow
- Webhook signing secret validated on every request — mandatory
- Redis-backed queues for all post-payment jobs
- Currency: RUB, single currency, stored as `currency char(3)` for future extensibility

### Dependencies
- Phase 3 (user must be logged in and email-verified to checkout)
- Phase 4 (books must exist with a price and be published)

### Git
- **Branch**: `feature/cart-payments`
- **Commit**: `feat: add cart, Stripe checkout, orders and webhook handling`

---

## Phase 6 — Digital Delivery (epub)

**Goal**: Paid users can download their epub. Download is secure, proxied, and time-limited.

### Features
- Download endpoint: `GET /books/{book}/download`
- Authorization: user must have a `user_book` record for this book (regardless of order status details)
- Controller generates a pre-signed S3 URL (TTL: configurable, default 5 min)
- Returns the signed URL to the client — client browser downloads directly from S3
- S3 URL is never exposed publicly — all download requests go through the controller
- Rate limiting: max 10 download requests per user per book per hour
- Download logged: `download_logs` table (user_id, book_id, downloaded_at, ip) — for analytics

### Key Technical Decisions
- `user_book` pivot: `user_id`, `book_id`, `order_id` (nullable for future manual grants), `granted_at`
- Latest epub always served (no file version tracking)
- Pre-signed URL TTL via `.env`: `DOWNLOAD_URL_TTL=300`
- Book file key: `books/{book_id}/book.epub` — not guessable but also not random (predictable for admin management; security via private bucket + signed URLs)

### Dependencies
- Phase 4 (epub uploaded to private S3 bucket)
- Phase 5 (`user_book` records created on payment)

### Git
- **Branch**: `feature/digital-delivery`
- **Commit**: `feat: add controller-proxied epub download with signed S3 URLs`

---

## Phase 7 — User Dashboard & Library

**Goal**: User has a personal cabinet showing owned books and order history.

### Features
- `/cabinet` — main dashboard
- `/cabinet/library` — list of purchased books: cover, title, "Download epub" button
- `/cabinet/orders` — order history: date, books purchased, amount, status
- Download from library triggers Phase 6 endpoint
- Already-purchased books show "In library" state on book detail and cart pages
- OAuth provider management: `/cabinet/settings` — list connected providers, connect new, disconnect (cannot disconnect last provider if no password set)

### Dependencies
- Phase 5 (orders and user_book records exist)
- Phase 6 (download endpoint)
- Phase 3 (OAuth provider linking UI)

### Git
- **Branch**: `feature/user-dashboard`
- **Commit**: `feat: add user cabinet with library, order history and OAuth provider management`

---

## Phase 8 — Blog / News

**Goal**: Author can publish articles. Supports SEO and reader retention.

### Features
- Post model: title, slug, body (rich text / plain textarea — same choice as Phase 4), excerpt, cover image, `status` (draft/published), `published_at`
- Public blog index: `/blog` — paginated, published only, sorted by `published_at` desc
- Post detail: `/blog/{slug}`
- Admin CRUD for posts (extends existing admin panel)
- No comments, no categories at this stage

### Key Technical Decisions
- `published_at` enables future-scheduling posts
- Slug unique at DB level
- Same `<textarea>` approach as books (consistency with Phase 4 decision)

### Dependencies
- Phase 4 (admin panel patterns)

### Git
- **Branch**: `feature/blog`
- **Commit**: `feat: add blog with admin CRUD and public listing`

---

## Phase 9 — SEO Layer

**Goal**: All public pages are discoverable and shareable.

### Features
- Meta title + description on all public pages
- OpenGraph tags on book pages, blog posts, homepage
- Twitter Card tags
- `sitemap.xml` — books (published) + blog posts (published) — regenerated on publish events
- `robots.txt` with `Disallow: /admin`, `Disallow: /cabinet`, `Disallow: /checkout`
- Canonical URLs on all pages
- JSON-LD structured data for books (Product schema)
- Slug-based URLs already in place from Phases 2 and 8

### Key Technical Decisions
- `spatie/laravel-sitemap` for sitemap generation
- Shared Blade meta component or `romanzinho/laravel-seo`
- Sitemap regenerated via Model observer on book/post status change

### Dependencies
- Phase 2 (books with slugs)
- Phase 8 (posts with slugs)

### Git
- **Branch**: `feature/seo`
- **Commit**: `feat: add meta tags, OpenGraph, sitemap and robots.txt`

---

## Phase 10 — Analytics & Event Tracking

**Goal**: Author understands user behavior for marketing analysis — page views, clicks, funnel drop-offs.

### Features

**Page-level analytics**
- Track: page views, unique visitors, session duration, referrer, device type
- Pages to track: homepage, catalog, each book detail, sample fragment, blog, cart, checkout

**Event tracking**
- "Add to cart" click (which book, from which page)
- "Checkout" initiated
- "Buy" button on book detail
- Sample fragment read (started / reached last page / "buy" CTA clicked)
- Download initiated

**Admin analytics view** (`/admin/analytics`)
- Top pages by views (last 7 / 30 days)
- Funnel: book detail → cart → checkout → paid
- Top books by add-to-cart and by purchase
- Download count per book

**Implementation**
- Option A (recommended): Self-hosted [Plausible Analytics](https://plausible.io) or Umami — privacy-friendly, GDPR-compliant, no cookie consent needed for basic stats, works behind a custom domain
- Option B: Custom event log table in MySQL (`analytics_events`: session_id, event, payload JSON, created_at) + simple admin aggregation queries
- Custom events (cart add, download, fragment CTA) always go through an internal Laravel endpoint `POST /analytics/event` → stored in DB regardless of analytics provider

### Key Technical Decisions
- Do NOT use Google Analytics (cookie consent complexity, data sovereignty issues under Russian law)
- Custom event endpoint feeds both the self-hosted analytics tool AND the internal DB for admin queries
- Plausible/Umami handles page views via script tag; custom events via their JS API
- No personal data stored in analytics events — session-based identifiers only

### Dependencies
- Phase 2 (pages exist)
- Phase 5 (checkout funnel exists)

### Git
- **Branch**: `feature/analytics`
- **Commit**: `feat: add analytics event tracking and admin analytics dashboard`

---

## Phase 11 — Admin Panel — Blog & Storefront

**Goal**: Author controls all content and storefront presentation without developer help.

### Features
- Blog post CRUD in admin (if not already in Phase 8)
- Storefront ordering: `sort_order` integer on books, editable from admin list (simple number input or up/down buttons)
- Bulk publish/unpublish books from admin list
- Featured flag toggle from admin list (controls carousel)
- Static page content management: admin can edit body text of static pages (Privacy, Terms, Offer, etc.) via a simple textarea — stored in a `static_pages` table
- Basic stats widget on admin dashboard: total orders, total revenue, books sold, top book

### Key Technical Decisions
- `static_pages` table: `slug`, `title`, `body` (TEXT) — seeded with default content from Phase 2
- Stats: Eloquent aggregates, no reporting package

### Dependencies
- Phase 4 (admin foundation)
- Phase 8 (blog)

### Git
- **Branch**: `feature/admin-content`
- **Commit**: `feat: add blog admin, storefront controls and static page editor`

---

## Phase 12 — Hardening & Monitoring

**Goal**: Application is production-ready. Secure, observable, backed up.

### Features
- Rate limiting: auth routes, checkout, download endpoint, analytics event endpoint
- Security headers: Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, Referrer-Policy
- Cookie consent banner (for any non-essential scripts/cookies — ties to Phase 10 analytics choice)
- Queue monitoring: Laravel Horizon (recommended) or Supervisor — config documented
- Telescope in dev only (never in prod)
- Structured JSON logging in prod
- Database query indexes reviewed and added where missing
- Cover image optimization on upload (resize to max dimensions, compress)
- Redis cache: catalog page, book detail, blog index — invalidated via Model observers
- Automated backups: `spatie/laravel-backup` — daily DB dump + S3 sync
- Load test: checkout flow and download endpoint

### Key Technical Decisions
- Horizon preferred over Supervisor for visibility into queue health
- Cache TTL: catalog 1h, book detail 6h, blog 30min — invalidated on any relevant model save

### Dependencies
- All previous phases

### Git
- **Branch**: `feature/hardening`
- **Commit**: `chore: security headers, caching, queue monitoring, backups and load testing`

---

## Milestones

### MVP — Phases 1–5
Working store: browse books, register (including OAuth), add to cart, pay. Author can manage books via admin.

**Exit criteria**:
- At least one book purchasable end-to-end via Stripe
- Admin can create, publish, and upload epub for a book
- Email verification working
- OAuth login working for at least one provider
- Deployed to production URL with HTTPS

---

### Beta — Phases 6–10
Purchases deliver epub. Users have a cabinet. Blog is live. SEO is in place. Analytics tracking what matters.

**Exit criteria**:
- Purchased epub downloadable from cabinet
- Sample fragment page live with copy protection and end-cap
- Blog live with at least one post
- Sitemap submitted to Google Search Console
- Analytics tracking add-to-cart → checkout → purchase funnel

---

### Production-ready — Phases 11–12
Author controls all content. Application is hardened, monitored, backed up.

**Exit criteria**:
- Author edits all static pages without developer help
- Horizon monitoring queue workers
- Daily backup confirmed working
- Security headers passing Mozilla Observatory scan (B+ minimum)

---

## Git Strategy Summary

| Phase | Branch | Commit message |
|-------|--------|----------------|
| 1 | `feature/project-foundation` | `chore: set up CI, Pint, Sentry, MinIO and health check` |
| 2 | `feature/storefront` | `feat: add homepage carousel, book catalog, book detail, sample fragment and static pages` |
| 3 | `feature/auth` | `feat: add auth with OAuth, email verification and provider linking` |
| 4 | `feature/admin-books` | `feat: add admin panel with book CRUD, draft/publish flow and epub upload` |
| 5 | `feature/cart-payments` | `feat: add cart, Stripe checkout, orders and webhook handling` |
| 6 | `feature/digital-delivery` | `feat: add controller-proxied epub download with signed S3 URLs` |
| 7 | `feature/user-dashboard` | `feat: add user cabinet with library, order history and OAuth provider management` |
| 8 | `feature/blog` | `feat: add blog with admin CRUD and public listing` |
| 9 | `feature/seo` | `feat: add meta tags, OpenGraph, sitemap and robots.txt` |
| 10 | `feature/analytics` | `feat: add analytics event tracking and admin analytics dashboard` |
| 11 | `feature/admin-content` | `feat: add blog admin, storefront controls and static page editor` |
| 12 | `feature/hardening` | `chore: security headers, caching, queue monitoring, backups and load testing` |

All branches merge to `master` via PR. No long-lived feature branches.

---

## Risks & Gaps (Resolved)

All blocking architectural questions from the initial review have been resolved. See **Locked Decisions** table at the top.

### Remaining Open Items (non-blocking)

| # | Item | Decision needed by |
|---|------|--------------------|
| 1 | Analytics provider: Plausible vs Umami vs custom | Before Phase 10 |
| 2 | Refund policy: does the author support epub refunds? | Before Phase 5 goes live |
| 3 | Social media links in footer: which platforms and URLs | Before Phase 2 |
| 4 | Email provider: Postmark / Mailgun / SES | Before Phase 5 (order confirmation emails) |
| 5 | Cookie consent: is Plausible used (no consent needed) or GA-style (consent banner required) | Before Phase 12 |
| 6 | VK/Instagram Socialite providers: confirm community package compatibility with current API versions | Before Phase 3 |

---

*End of roadmap. Send any phase to the architect for DB/API design before implementation begins.*
