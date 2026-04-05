# Architecture Blueprint — Digital Bookshop

**Project**: Single-author Russian-language digital bookshop
**Stack**: Laravel 12, PHP 8.4, MySQL 8, Redis, Docker, S3, Stripe
**Last updated**: 2026-04-03

---

## Application Structure

All application code is organized into **feature slices** under `app/Features/`. Each feature owns its controllers, services, requests, jobs, events, listeners, and mail:

| Feature | Namespace prefix | Responsibility |
|---------|-----------------|----------------|
| `Admin` | `App\Features\Admin` | Book management, admin dashboard |
| `Auth` | `App\Features\Auth` | Login, registration, OAuth, password |
| `Cabinet` | `App\Features\Cabinet` | User profile, library, orders views |
| `Cart` | `App\Features\Cart` | Guest/auth cart, merge on login |
| `Catalog` | `App\Features\Catalog` | Public book catalog and pages |
| `Checkout` | `App\Features\Checkout` | Orders, Stripe, webhooks, email |
| `Download` | `App\Features\Download` | Epub download via pre-signed S3 URL |

**Shared code** (Models, Policies, Enums, Providers) stays in `app/` root — these are not feature-specific.

New features should follow the same slice pattern: `app/Features/{FeatureName}/{Controllers|Services|Requests|...}/`.

---

## Locked Answers to Architect Clarifying Questions

| # | Question | Answer | Schema impact |
|---|----------|--------|---------------|
| 1 | Refunds | Via Stripe Dashboard only | No new admin routes |
| 2 | Newsletter | Own logic, external SMTP for sending | `newsletter_subscribers` table; no 3rd-party API |
| 3 | Blog post cover | Optional | `cover_path` nullable, no required validation on publish |
| 4 | OAuth email form (no email from provider) | Inline on callback page | No separate route; callback controller handles the inline form |
| 5 | Static pages set | Fixed (≤10); author edits rarely | No create/delete admin routes; only edit |
| 6 | Admin order list | Yes | `GET /admin/orders`, `GET /admin/orders/{order}` + controller |
| 7 | Cart cleanup | Yes, scheduled command (or Redis TTL for guest carts) | `app:cleanup-carts` Artisan command |
| 8 | Pending order expiration | Yes, mark as `failed` after timeout | `app:expire-pending-orders` Artisan command |
| 9 | Cover image sizes | Two: full + thumbnail | Two columns: `cover_path`, `cover_thumb_path` on books and posts |
| 10 | Blog post body format | HTML + server-side sanitization | Use `ezyang/htmlpurifier`; `body` stored as sanitized HTML |

---

## Phase 1 — Project Foundation & CI

### Schema

No application tables introduced. This phase uses only Laravel's default tables (see Phase 3 for users).

### Routes

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/up` | — (Laravel default health check) | — |

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| — | — | No application classes. Phase 1 is CI, config, Docker (MinIO), Pint, Sentry integration. |

---

## Phase 2 — Storefront & Static Pages

### Schema

**Book** (`books`)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UNSIGNED BIGINT AUTO_INCREMENT | NO | — | PK |
| `title` | VARCHAR(255) | NO | — | Book title |
| `slug` | VARCHAR(255) | NO | — | URL slug |
| `annotation` | TEXT | YES | NULL | Short marketing description (1-3 sentences) |
| `excerpt` | TEXT | YES | NULL | Longer preview / back-cover text |
| `fragment` | LONGTEXT | YES | NULL | Full sample fragment for the fragment page |
| `price` | UNSIGNED INT | NO | 0 | Price in kopecks |
| `currency` | CHAR(3) | NO | 'RUB' | ISO 4217 currency code |
| `cover_path` | VARCHAR(255) | YES | NULL | S3 public disk path to full-size cover image |
| `cover_thumb_path` | VARCHAR(255) | YES | NULL | S3 public disk path to thumbnail cover image |
| `epub_path` | VARCHAR(255) | YES | NULL | S3 private disk path to epub file |
| `status` | VARCHAR(20) | NO | 'draft' | BookStatus enum: draft, published |
| `is_featured` | TINYINT(1) | NO | 0 | Show in homepage carousel |
| `sort_order` | UNSIGNED INT | NO | 0 | Display order (lower = first) |
| `created_at` | TIMESTAMP | YES | NULL | Laravel timestamp |
| `updated_at` | TIMESTAMP | YES | NULL | Laravel timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE INDEX `books_slug_unique` (`slug`)
- INDEX `books_status_sort_order_index` (`status`, `sort_order`)
- INDEX `books_is_featured_index` (`is_featured`)

**Foreign keys:** none

### Routes

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/` | HomeController@index | web |
| GET | `/books` | BookController@index | web |
| GET | `/books/{book:slug}` | BookController@show | web |
| GET | `/books/{book:slug}/fragment` | BookController@fragment | web |
| GET | `/about` | StaticPageController@show | web |
| GET | `/privacy` | StaticPageController@show | web |
| GET | `/terms` | StaticPageController@show | web |
| GET | `/offer` | StaticPageController@show | web |
| GET | `/personal-data` | StaticPageController@show | web |
| GET | `/newsletter-consent` | StaticPageController@show | web |
| GET | `/cookies` | StaticPageController@show | web |
| GET | `/refund` | StaticPageController@show | web |
| GET | `/contacts` | StaticPageController@show | web |
| GET | `/payment-info` | StaticPageController@show | web |

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| Migration | 2026_03_20_000001_create_books_table | Create books table (includes cover_thumb_path) |
| Model | Book | Eloquent model for books with status enum cast |
| Enum | BookStatus | String-backed enum: draft, published |
| Controller | HomeController | Serve homepage with featured books carousel |
| Controller | BookController | Serve catalog index, book detail, and fragment pages |
| Controller | StaticPageController | Serve static pages by slug from Blade views |

---

## Phase 3 — User Authentication & OAuth

### Schema

**User** (`users`) — modifies Laravel default

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UNSIGNED BIGINT AUTO_INCREMENT | NO | — | PK |
| `name` | VARCHAR(255) | NO | — | Display name |
| `email` | VARCHAR(255) | NO | — | Login email |
| `email_verified_at` | TIMESTAMP | YES | NULL | Email verification timestamp |
| `password` | VARCHAR(255) | YES | NULL | Nullable for OAuth-only users |
| `role` | VARCHAR(20) | NO | 'user' | UserRole enum: user, admin |
| `newsletter_consent` | TINYINT(1) | NO | 0 | Newsletter opt-in |
| `remember_token` | VARCHAR(100) | YES | NULL | Laravel remember token |
| `created_at` | TIMESTAMP | YES | NULL | Laravel timestamp |
| `updated_at` | TIMESTAMP | YES | NULL | Laravel timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE INDEX `users_email_unique` (`email`)

**Foreign keys:** none

**OAuthProvider** (`oauth_providers`)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UNSIGNED BIGINT AUTO_INCREMENT | NO | — | PK |
| `user_id` | UNSIGNED BIGINT | NO | — | FK to users.id |
| `provider` | VARCHAR(30) | NO | — | Provider name: google, vk, instagram, facebook |
| `provider_id` | VARCHAR(255) | NO | — | Provider's user ID |
| `token` | TEXT | YES | NULL | OAuth access token |
| `refresh_token` | TEXT | YES | NULL | OAuth refresh token |
| `created_at` | TIMESTAMP | YES | NULL | Laravel timestamp |
| `updated_at` | TIMESTAMP | YES | NULL | Laravel timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE INDEX `oauth_providers_provider_provider_id_unique` (`provider`, `provider_id`)
- INDEX `oauth_providers_user_id_index` (`user_id`)

**Foreign keys:**
- `user_id` REFERENCES `users`(`id`) ON DELETE CASCADE

### Routes

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/register` | Auth\RegisteredUserController@create | web, guest |
| POST | `/register` | Auth\RegisteredUserController@store | web, guest |
| GET | `/login` | Auth\AuthenticatedSessionController@create | web, guest |
| POST | `/login` | Auth\AuthenticatedSessionController@store | web, guest |
| POST | `/logout` | Auth\AuthenticatedSessionController@destroy | web, auth |
| GET | `/forgot-password` | Auth\PasswordResetLinkController@create | web, guest |
| POST | `/forgot-password` | Auth\PasswordResetLinkController@store | web, guest |
| GET | `/reset-password/{token}` | Auth\NewPasswordController@create | web, guest |
| POST | `/reset-password` | Auth\NewPasswordController@store | web, guest |
| GET | `/email/verify` | Auth\EmailVerificationPromptController@__invoke | web, auth |
| GET | `/email/verify/{id}/{hash}` | Auth\VerifyEmailController@__invoke | web, auth, signed, throttle:6,1 |
| POST | `/email/verification-notification` | Auth\EmailVerificationNotificationController@store | web, auth, throttle:6,1 |
| GET | `/auth/{provider}/redirect` | OAuthController@redirect | web, guest |
| GET | `/auth/{provider}/callback` | OAuthController@callback | web, guest |

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| Migration | 2026_03_20_000002_modify_users_table_add_role_and_newsletter | Add role, newsletter_consent columns; make password nullable |
| Migration | 2026_03_20_000003_create_oauth_providers_table | Create oauth_providers table |
| Model | User | Eloquent model with MustVerifyEmail, role cast to UserRole enum |
| Model | OAuthProvider | Eloquent model for linked OAuth providers |
| Enum | UserRole | String-backed enum: user, admin |
| Controller | OAuthController | Handle OAuth redirect and callback for all providers |
| Controller | Auth\RegisteredUserController | Registration form and creation (Breeze scaffold, modified) |
| Controller | Auth\AuthenticatedSessionController | Login/logout (Breeze scaffold, modified) |
| Controller | Auth\PasswordResetLinkController | Password reset request (Breeze scaffold) |
| Controller | Auth\NewPasswordController | Password reset execution (Breeze scaffold) |
| Controller | Auth\EmailVerificationPromptController | Show email verification notice (Breeze scaffold) |
| Controller | Auth\VerifyEmailController | Handle email verification link (Breeze scaffold) |
| Controller | Auth\EmailVerificationNotificationController | Resend verification email (Breeze scaffold) |
| Request | RegisterRequest | Validate name, email, password, terms acceptance, newsletter consent |
| Request | LoginRequest | Validate email, password (Breeze scaffold) |
| Service | OAuthService | Handle OAuth user resolution: find-or-create user, link provider, merge logic |
| Middleware | EnsureAdmin | Check user role is admin; return 404 if not |

---

## Phase 4 — Admin Panel — Books

### Schema

No new tables. Phase 2 books table is used. Admin operates on existing `books` table.

### Routes

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/admin` | Admin\DashboardController@index | web, auth, admin |
| GET | `/admin/books` | Admin\BookController@index | web, auth, admin |
| GET | `/admin/books/create` | Admin\BookController@create | web, auth, admin |
| POST | `/admin/books` | Admin\BookController@store | web, auth, admin |
| GET | `/admin/books/{book}/edit` | Admin\BookController@edit | web, auth, admin |
| PUT | `/admin/books/{book}` | Admin\BookController@update | web, auth, admin |
| DELETE | `/admin/books/{book}` | Admin\BookController@destroy | web, auth, admin |
| PATCH | `/admin/books/{book}/toggle-status` | Admin\BookController@toggleStatus | web, auth, admin |
| PATCH | `/admin/books/{book}/toggle-featured` | Admin\BookController@toggleFeatured | web, auth, admin |

Note: `admin` refers to the `EnsureAdmin` middleware alias registered in Phase 3.

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| Controller | Admin\DashboardController | Render admin dashboard with stats widget |
| Controller | Admin\BookController | Full CRUD for books including cover/epub upload |
| Request | Admin\StoreBookRequest | Validate book creation: title, slug, price, status, cover, epub, annotation, excerpt, fragment, is_featured, sort_order |
| Request | Admin\UpdateBookRequest | Validate book update: same fields as store, slug unique ignoring current |
| Service | BookFileService | Handle cover image upload to s3-public and epub upload to s3-private |
| Job | ProcessBookFileUpload | Queue epub upload to S3 to avoid request timeout |
| Policy | BookPolicy | Authorize book deletion (only draft books with no purchases) |

---

## Phase 5 — Cart & Payments (Stripe)

### Schema

**CartItem** (`cart_items`)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UNSIGNED BIGINT AUTO_INCREMENT | NO | — | PK |
| `user_id` | UNSIGNED BIGINT | YES | NULL | FK to users.id; null for guests |
| `session_id` | VARCHAR(255) | YES | NULL | Session ID for guest carts |
| `book_id` | UNSIGNED BIGINT | NO | — | FK to books.id |
| `created_at` | TIMESTAMP | YES | NULL | Laravel timestamp |
| `updated_at` | TIMESTAMP | YES | NULL | Laravel timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE INDEX `cart_items_user_id_book_id_unique` (`user_id`, `book_id`) — prevents duplicate items for authenticated users
- UNIQUE INDEX `cart_items_session_id_book_id_unique` (`session_id`, `book_id`) — prevents duplicate items for guests
- INDEX `cart_items_session_id_index` (`session_id`)
- INDEX `cart_items_book_id_index` (`book_id`)

**Foreign keys:**
- `user_id` REFERENCES `users`(`id`) ON DELETE CASCADE
- `book_id` REFERENCES `books`(`id`) ON DELETE CASCADE

**Order** (`orders`)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UNSIGNED BIGINT AUTO_INCREMENT | NO | — | PK |
| `user_id` | UNSIGNED BIGINT | NO | — | FK to users.id |
| `status` | VARCHAR(20) | NO | 'pending' | OrderStatus enum: pending, paid, refunded, failed |
| `total_amount` | UNSIGNED INT | NO | 0 | Total in kopecks |
| `currency` | CHAR(3) | NO | 'RUB' | ISO 4217 currency code |
| `payment_provider` | VARCHAR(30) | NO | 'stripe' | Payment provider identifier |
| `stripe_session_id` | VARCHAR(255) | YES | NULL | Stripe Checkout Session ID |
| `stripe_payment_intent_id` | VARCHAR(255) | YES | NULL | Stripe PaymentIntent ID |
| `paid_at` | TIMESTAMP | YES | NULL | When payment was confirmed |
| `created_at` | TIMESTAMP | YES | NULL | Laravel timestamp |
| `updated_at` | TIMESTAMP | YES | NULL | Laravel timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- INDEX `orders_user_id_index` (`user_id`)
- INDEX `orders_status_index` (`status`)
- UNIQUE INDEX `orders_stripe_session_id_unique` (`stripe_session_id`)

**Foreign keys:**
- `user_id` REFERENCES `users`(`id`) ON DELETE RESTRICT

**OrderItem** (`order_items`)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UNSIGNED BIGINT AUTO_INCREMENT | NO | — | PK |
| `order_id` | UNSIGNED BIGINT | NO | — | FK to orders.id |
| `book_id` | UNSIGNED BIGINT | NO | — | FK to books.id |
| `price` | UNSIGNED INT | NO | 0 | Price at time of purchase in kopecks |
| `currency` | CHAR(3) | NO | 'RUB' | ISO 4217 currency code |
| `created_at` | TIMESTAMP | YES | NULL | Laravel timestamp |
| `updated_at` | TIMESTAMP | YES | NULL | Laravel timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- INDEX `order_items_order_id_index` (`order_id`)
- INDEX `order_items_book_id_index` (`book_id`)

**Foreign keys:**
- `order_id` REFERENCES `orders`(`id`) ON DELETE CASCADE
- `book_id` REFERENCES `books`(`id`) ON DELETE RESTRICT

**UserBook** (`user_books`)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UNSIGNED BIGINT AUTO_INCREMENT | NO | — | PK |
| `user_id` | UNSIGNED BIGINT | NO | — | FK to users.id |
| `book_id` | UNSIGNED BIGINT | NO | — | FK to books.id |
| `order_id` | UNSIGNED BIGINT | YES | NULL | FK to orders.id; nullable for future manual grants |
| `granted_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | When ownership was granted |
| `created_at` | TIMESTAMP | YES | NULL | Laravel timestamp |
| `updated_at` | TIMESTAMP | YES | NULL | Laravel timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE INDEX `user_books_user_id_book_id_unique` (`user_id`, `book_id`)
- INDEX `user_books_book_id_index` (`book_id`)
- INDEX `user_books_order_id_index` (`order_id`)

**Foreign keys:**
- `user_id` REFERENCES `users`(`id`) ON DELETE CASCADE
- `book_id` REFERENCES `books`(`id`) ON DELETE RESTRICT
- `order_id` REFERENCES `orders`(`id`) ON DELETE SET NULL

### Routes

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/cart` | CartController@index | web |
| POST | `/cart/{book}` | CartController@store | web |
| DELETE | `/cart/{book}` | CartController@destroy | web |
| POST | `/checkout` | CheckoutController@store | web, auth, verified |
| GET | `/checkout/success` | CheckoutController@success | web, auth, verified |
| GET | `/checkout/status/{order}` | CheckoutController@status | web, auth, verified |
| POST | `/webhooks/stripe` | WebhookController@handleStripe | — |

Note: The webhook route has NO middleware (no CSRF, no auth). Stripe signature verification is done inside the controller.

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| Migration | 2026_03_20_000004_create_cart_items_table | Create cart_items table |
| Migration | 2026_03_20_000005_create_orders_table | Create orders table |
| Migration | 2026_03_20_000006_create_order_items_table | Create order_items table |
| Migration | 2026_03_20_000007_create_user_books_table | Create user_books table |
| Model | CartItem | Eloquent model for cart items |
| Model | Order | Eloquent model for orders with status enum cast |
| Model | OrderItem | Eloquent model for order line items |
| Model | UserBook | Eloquent model for book ownership pivot |
| Enum | OrderStatus | String-backed enum: pending, paid, refunded, failed |
| Controller | CartController | Add/remove books from cart, display cart page |
| Controller | CheckoutController | Create Stripe session, handle success redirect, poll status |
| Controller | WebhookController | Receive and process Stripe webhooks |
| Service | CartService | Cart logic: add, remove, merge guest cart to user, get items with totals |
| Service | PaymentProvider | Interface: createSession(Order, User): string, handleWebhook(Request): void |
| Service | StripePaymentProvider | Stripe implementation of PaymentProvider interface |
| Service | OrderService | Create order from cart, transition statuses, grant book ownership |
| Job | ProcessPaymentConfirmation | Queue: mark order paid, create user_books, clear cart, dispatch email |
| Event | OrderPaid | Fired when order transitions to paid status |
| Listener | SendOrderConfirmationEmail | Listens to OrderPaid, queues confirmation email |
| Mail | OrderConfirmationMail | Mailable: order confirmation with list of purchased books |

### Implementation Sub-phases

Phase 5 is split into 5 sessions. Each session depends on the previous.

**5.1 — Data Layer** *(backend)*
Migrations, models, enums, factories. No business logic.
- Migrations: `cart_items`, `orders`, `order_items`, `user_books`
- Models: `CartItem`, `Order`, `OrderItem`, `UserBook`
- Enum: `OrderStatus` (pending, paid, refunded, failed)
- Factories for all 4 models

**5.2 — Cart** *(backend + frontend)*
Full cart cycle, no payments.
- `CartService` — add, remove, merge guest→user cart, totals, Rule 23
- `CartController` — index, store, destroy
- Cart page UI: empty / guest / auth-unverified / auth-verified states
- Tests for all cart operations

**5.3 — Order Creation + Stripe Session** *(backend)*
Order creation and Stripe redirect. Invoke architect before starting — new infrastructure.
- `PaymentProvider` interface + `StripePaymentProvider::createSession()`
- `OrderService::createFromCart()` — order + order_items + price snapshot
- `CheckoutController@store` — create order → redirect to Stripe
- Tests: Rules 27, 28

**5.4 — Webhook + Book Grant** *(backend)*
Payment source of truth. Most critical sub-phase.
- `WebhookController` — Stripe signature verification, Rule 35
- `ProcessPaymentConfirmation` job — mark paid, create `user_books`, clear cart, dispatch email
- `OrderPaid` event → `SendOrderConfirmationEmail` listener → `OrderConfirmationMail`
- `CheckoutController@success` + `@status` (polling endpoint)
- Tests: webhook idempotency, Rules 29, 30

**5.5 — Checkout Frontend** *(frontend)*
UI after all backend is complete.
- Success/pending pages with Alpine.js polling (every 2s, max 30s)
- Order confirmation email template
- Book card "В библиотеке" state after purchase

---

## Phase 6 — Digital Delivery (epub)

### Schema

**DownloadLog** (`download_logs`)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UNSIGNED BIGINT AUTO_INCREMENT | NO | — | PK |
| `user_id` | UNSIGNED BIGINT | NO | — | FK to users.id |
| `book_id` | UNSIGNED BIGINT | NO | — | FK to books.id |
| `ip_address` | VARCHAR(45) | NO | — | Client IP (supports IPv6) |
| `downloaded_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | When download was initiated |

**Indexes:**
- PRIMARY KEY (`id`)
- INDEX `download_logs_user_id_book_id_index` (`user_id`, `book_id`)
- INDEX `download_logs_downloaded_at_index` (`downloaded_at`)

**Foreign keys:**
- `user_id` REFERENCES `users`(`id`) ON DELETE CASCADE
- `book_id` REFERENCES `books`(`id`) ON DELETE RESTRICT

### Routes

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/books/{book}/download` | DownloadController@show | web, auth, verified, throttle:download |

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| Migration | 2026_03_20_000008_create_download_logs_table | Create download_logs table |
| Model | DownloadLog | Eloquent model for download audit trail |
| Controller | DownloadController | Verify ownership, generate pre-signed S3 URL, log download, redirect |
| Service | DownloadService | Generate pre-signed URL from s3-private disk, log download |
| Policy | DownloadPolicy | Authorize: user must own book (user_books record exists) |

---

## Phase 7 — User Dashboard & Library

### Schema

No new tables. This phase uses `user_books`, `orders`, `order_items`, `oauth_providers`.

### Routes

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/cabinet` | CabinetController@index | web, auth, verified |
| GET | `/cabinet/library` | CabinetController@library | web, auth, verified |
| GET | `/cabinet/orders` | CabinetController@orders | web, auth, verified |
| GET | `/cabinet/settings` | SettingsController@edit | web, auth, verified |
| PUT | `/cabinet/settings` | SettingsController@update | web, auth, verified |
| PUT | `/cabinet/settings/password` | SettingsController@updatePassword | web, auth, verified |
| POST | `/cabinet/settings/oauth/{provider}` | SettingsController@linkProvider | web, auth, verified |
| DELETE | `/cabinet/settings/oauth/{provider}` | SettingsController@unlinkProvider | web, auth, verified |

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| Controller | CabinetController | Display library (owned books), order history, dashboard redirect |
| Controller | SettingsController | Profile editing, password change, OAuth provider linking/unlinking |
| Request | UpdateProfileRequest | Validate name update |
| Request | UpdatePasswordRequest | Validate current password, new password, confirmation |

### Implementation Sub-phases

Phase 7 is split into 2 sessions. Each session depends on the previous.

**7.1 — Backend** *(backend)*
Controllers, form requests, routes. No new migrations.
- `CabinetController@index` — redirects to `/cabinet/library` (Rule 42)
- `CabinetController@library` — owned books from `user_books` with download buttons (Rule 43)
- `CabinetController@orders` — paginated order history (10/page, created_at DESC, Rule 44)
- `SettingsController@edit` — show profile + password form + linked OAuth providers
- `SettingsController@update` — update name only; email is read-only (Rule 46)
- `SettingsController@updatePassword` — only if user has a password set (Rule 47)
- `SettingsController@linkProvider` — link OAuth provider to existing account
- `SettingsController@unlinkProvider` — Rule 45: cannot unlink last auth method if no password set
- `UpdateProfileRequest`, `UpdatePasswordRequest` form requests
- Tests: all happy paths + Rule 45 edge case + Rule 47 edge case

**7.2 — Frontend** *(frontend)*
Blade views for all cabinet pages. Backend must be complete first.
- `/cabinet/library` — book grid with cover, title, download button per owned book
- `/cabinet/orders` — paginated order list with status badge, items, total
- `/cabinet/settings` — profile form, password change form, linked OAuth providers list

---

## Phase 8 — Blog / News

### Schema

**Post** (`posts`)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UNSIGNED BIGINT AUTO_INCREMENT | NO | — | PK |
| `title` | VARCHAR(255) | NO | — | Post title |
| `slug` | VARCHAR(255) | NO | — | URL slug |
| `excerpt` | TEXT | YES | NULL | Short summary for blog index |
| `body` | LONGTEXT | NO | — | Full post content — sanitized HTML (HtmlPurifier on save) |
| `cover_path` | VARCHAR(255) | YES | NULL | S3 public disk path to full-size cover image |
| `cover_thumb_path` | VARCHAR(255) | YES | NULL | S3 public disk path to thumbnail cover image |
| `status` | VARCHAR(20) | NO | 'draft' | PostStatus enum: draft, published |
| `published_at` | TIMESTAMP | YES | NULL | Publication date; future = scheduled |
| `created_at` | TIMESTAMP | YES | NULL | Laravel timestamp |
| `updated_at` | TIMESTAMP | YES | NULL | Laravel timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE INDEX `posts_slug_unique` (`slug`)
- INDEX `posts_status_published_at_index` (`status`, `published_at`)

**Foreign keys:** none

### Routes

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/blog` | PostController@index | web |
| GET | `/blog/{post:slug}` | PostController@show | web |

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| Migration | 2026_03_20_000009_create_posts_table | Create posts table (includes cover_thumb_path) |
| Model | Post | Eloquent model for blog posts with status enum cast |
| Enum | PostStatus | String-backed enum: draft, published |
| Controller | PostController | Serve public blog index (paginated) and individual post pages |
| Service | HtmlSanitizerService | Wrap HtmlPurifier; sanitize post body HTML before save. **Must be called in Phase 11 `Admin\PostController` (or a PostAdminService) on store/update — not wired to the model.** |

---

## Phase 9 — SEO Layer

### Schema

No new tables.

### Implementation Sub-phases

Phase 9 is split into 2 sessions.

**9.1 — Meta Tags + OpenGraph + JSON-LD** *(frontend)*

- Shared Blade component `<x-seo>` — accepts title, description, canonical, og:image
- Meta title + description on all public pages (home, catalog, book detail, fragment, blog index, blog post, static pages)
- OpenGraph tags: `og:title`, `og:description`, `og:image`, `og:url`, `og:type` on book pages, blog posts, homepage
- Twitter Card tags on book pages and blog posts
- Canonical URL tag on all public pages
- JSON-LD structured data (Product schema) on book detail pages
- No new PHP classes — frontend only

**9.2 — Sitemap + robots.txt** *(backend)*

- Install `spatie/laravel-sitemap`
- `SitemapController@index` — generates sitemap XML with published books and posts
- `BookObserver` — regenerate sitemap cache when book status changes to/from published
- `PostObserver` — regenerate sitemap cache when post status changes to/from published
- `GenerateSitemapCommand` — Artisan command `app:generate-sitemap` to rebuild sitemap manually
- `robots.txt` served as static file: `Disallow: /admin`, `/cabinet`, `/checkout`
- Route: `GET /sitemap.xml` → SitemapController@index

### Routes

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/sitemap.xml` | SitemapController@index | web |
| GET | `/robots.txt` | — (static file in `public/`) | — |

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| Component | `resources/views/components/seo.blade.php` | Shared meta/OG/Twitter/canonical tags — accepts title, description, canonical, og_image |
| Controller | SitemapController | Generate sitemap XML with published books and posts |
| Observer | BookObserver | Regenerate sitemap cache when book status changes |
| Observer | PostObserver | Regenerate sitemap cache when post status changes |
| Command | GenerateSitemapCommand | Artisan command `app:generate-sitemap` to rebuild sitemap |

---

## Phase 10 — Analytics (Google Analytics 4)

> **Implemented as part of Phase 11.5 (Frontend).** No separate phase or PR needed.

No backend code, no database schema. Analytics is handled entirely by Google Analytics 4 via client-side script tags and `gtag()` calls.

**Global script** — added once to `resources/views/layouts/app.blade.php` in `<head>`. GA Measurement ID stored in `.env` as `GOOGLE_ANALYTICS_ID`, exposed via `config/services.php`. Script is skipped when ID is empty (local/test environments).

**Custom events:**

| Event | Where | Trigger |
|-------|-------|---------|
| `add_to_cart` | `books/show.blade.php` | Add to cart button click |
| `begin_checkout` | `cart/index.blade.php` | Checkout button click |
| `purchase` | `checkout/success.blade.php` | On page load when order is paid |
| `file_download` | `cabinet/library.blade.php` | Download button click |

Page views, sessions, funnels, and top-content reports provided by GA4 out of the box. GA4 account and Measurement ID are configured when deploying to production (domain required).

---

## Phase 11 — Admin Panel — Blog, Users & Orders

### Schema changes

**users** — add column:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `banned_at` | TIMESTAMP | YES | NULL | Set when admin bans a user; NULL = active |

**user_books** — add column:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `revoked_at` | TIMESTAMP | YES | NULL | Set when admin revokes access; NULL = active |

Note: `OrderStatus::Refunded` already exists in the enum — no migration needed for orders. Refunded status is set manually by admin after processing the refund in Stripe.

**Newsletter — Resend Audiences (no local table)**

Subscriber management is delegated entirely to **Resend Audiences**. No `newsletter_subscribers` table, no token columns, no batch jobs.

- Resend handles: double opt-in, unsubscribe links, bounce/spam handling, delivery stats
- Our side: API calls to add/remove contacts from a Resend Audience
- Audience ID stored in `.env` as `RESEND_AUDIENCE_ID`

Contacts are added to Resend Audience when:
1. User registers with `newsletter_consent = true`
2. User submits standalone subscribe form on public pages

Contacts are removed when user unsubscribes via Resend's built-in unsubscribe link (handled by Resend, no route needed on our side).

### Routes

**Admin — Blog:**

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/admin/posts` | Admin\PostController@index | web, auth, admin |
| GET | `/admin/posts/create` | Admin\PostController@create | web, auth, admin |
| POST | `/admin/posts` | Admin\PostController@store | web, auth, admin |
| GET | `/admin/posts/{post}/edit` | Admin\PostController@edit | web, auth, admin |
| PUT | `/admin/posts/{post}` | Admin\PostController@update | web, auth, admin |
| DELETE | `/admin/posts/{post}` | Admin\PostController@destroy | web, auth, admin |
| PATCH | `/admin/posts/{post}/toggle-status` | Admin\PostController@toggleStatus | web, auth, admin |

**Admin — Users:**

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/admin/users` | Admin\UserController@index | web, auth, admin |
| GET | `/admin/users/{user}` | Admin\UserController@show | web, auth, admin |
| PATCH | `/admin/users/{user}/ban` | Admin\UserController@ban | web, auth, admin |
| PATCH | `/admin/users/{user}/unban` | Admin\UserController@unban | web, auth, admin |
| POST | `/admin/users/{user}/send-password-reset` | Admin\UserController@sendPasswordReset | web, auth, admin |
| POST | `/admin/users/{user}/verify-email` | Admin\UserController@verifyEmail | web, auth, admin |

**Admin — Orders:**

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/admin/orders` | Admin\OrderController@index | web, auth, admin |
| GET | `/admin/orders/{order}` | Admin\OrderController@show | web, auth, admin |
| PATCH | `/admin/orders/{order}/refund` | Admin\OrderController@refund | web, auth, admin |

**Admin — User Books (troubleshooting):**

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| PATCH | `/admin/user-books/{userBook}/revoke` | Admin\UserBookController@revoke | web, auth, admin |
| PATCH | `/admin/user-books/{userBook}/restore` | Admin\UserBookController@restore | web, auth, admin |
| POST | `/admin/users/{user}/grant-book` | Admin\UserBookController@grant | web, auth, admin |

**Admin — Download Logs:**

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/admin/download-logs` | Admin\DownloadLogController@index | web, auth, admin |

**Admin — Newsletter:**

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| GET | `/admin/newsletter` | Admin\NewsletterController@index | web, auth, admin |
| POST | `/admin/newsletter/send` | Admin\NewsletterController@send | web, auth, admin |

**Public — Newsletter subscribe:**

| Method | URI | Controller@method | Middleware |
|--------|-----|-------------------|------------|
| POST | `/newsletter/subscribe` | NewsletterController@subscribe | web, throttle:5,1 |

Note: Unsubscribe and confirm links are handled by Resend — no local routes needed.

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| Migration | add_banned_at_to_users_table | Add `banned_at` nullable timestamp to users |
| Migration | add_revoked_at_to_user_books_table | Add `revoked_at` nullable timestamp to user_books |
| Migration | add_banned_at_to_users_table | Add `banned_at` nullable timestamp to users |
| Migration | add_revoked_at_to_user_books_table | Add `revoked_at` nullable timestamp to user_books |
| Controller | Admin\PostController | Full CRUD for blog posts. **Must call `HtmlSanitizerService::sanitize()` on `body` before saving.** |
| Controller | Admin\UserController | List users, view profile, ban/unban, send password reset, manually verify email |
| Controller | Admin\OrderController | List all orders, show detail, mark as refunded (triggers UserBook revoke for that order) |
| Controller | Admin\UserBookController | Grant/revoke/restore user book access for troubleshooting |
| Controller | Admin\DownloadLogController | List download logs with filter by user and book |
| Controller | Admin\NewsletterController | Show Resend Audience subscriber count; compose and send newsletter via Resend Broadcasts API |
| Controller | NewsletterController | Add contact to Resend Audience on public subscribe form submit |
| Request | Admin\StorePostRequest | Validate post creation: title, slug, excerpt, body, cover, status, published_at |
| Request | Admin\UpdatePostRequest | Validate post update: same fields, slug unique ignoring current |
| Request | Admin\SendNewsletterRequest | Validate newsletter send: subject, body, confirm_send checkbox |
| Request | Admin\GrantBookRequest | Validate book grant: book_id, reason (optional) |
| Service | Admin\PostAdminService | Create/update/delete posts with HTML sanitization via HtmlSanitizerService |
| Service | Admin\UserAdminService | Ban/unban user; send password reset link; mark email verified |
| Service | Admin\OrderAdminService | Mark order as refunded + revoke associated user_books in one transaction |
| Service | Admin\UserBookAdminService | Grant/revoke/restore user book access |
| Service | NewsletterService | Add/remove contacts in Resend Audience via API; send broadcast via Resend Broadcasts API |
| Command | CleanupCartsCommand | `app:cleanup-carts` — delete guest cart items older than 7 days (scheduled daily) |
| Command | ExpirePendingOrdersCommand | `app:expire-pending-orders` — mark pending orders as failed after 1 hour (scheduled every 15 min) |
| Middleware | CheckNotBanned | Abort 403 if `auth()->user()->banned_at` is set; registered on `web` group |

### Business rules

76. `banned_at` is set to `now()` on ban, `null` on unban. `CheckNotBanned` middleware aborts with 403 on every authenticated request if the user is banned.
77. Admin cannot ban themselves.
78. Password reset is sent via `Password::sendResetLink()` — no custom flow needed.
79. Email manual verification calls `$user->markEmailAsVerified()` and fires `Verified` event.
80. Refund flow: admin marks order `Refunded` + all `user_books` rows for that order get `revoked_at = now()`. Done in a single DB transaction via `OrderAdminService::refund()`.
81. Revoked `user_books` records are soft-revoked (history preserved). The download endpoint must check `revoked_at IS NULL` in addition to the existing ownership check.
82. Manual book grant creates a `user_books` record with `order_id = null` and `granted_at = now()`.
83. Blog post body is sanitized via `HtmlSanitizerService` on every store/update in `PostAdminService`.
84. Newsletter subscriber management is delegated to Resend Audiences — no local `newsletter_subscribers` table, no tokens, no batch jobs.
85. Contacts are added to Resend Audience on registration (if `newsletter_consent = true`) and via public subscribe form. `RESEND_AUDIENCE_ID` stored in `.env`.
86. Newsletter sending uses Resend Broadcasts API — Resend handles batching, unsubscribe links, bounce handling, and delivery stats.
87. `CleanupCartsCommand` runs daily; `ExpirePendingOrdersCommand` runs every 15 minutes — both registered in `routes/console.php` scheduler.

### Implementation sub-phases

**11.1 — Data layer**
- Migrations: `banned_at` on users, `revoked_at` on user_books
- Model updates: User (`isBanned()`, `banned_at` cast), UserBook (`isRevoked()`, `revoked_at` cast)
- `CheckNotBanned` middleware registered on `web` group
- Update download endpoint to check `revoked_at IS NULL`

**11.2 — Blog admin backend**
- `PostAdminService` with HtmlSanitizerService wired in
- `Admin\PostController` full CRUD + toggleStatus
- Form Requests: `StorePostRequest`, `UpdatePostRequest`

**11.3 — Users, Orders, UserBooks, DownloadLogs backend**
- `UserAdminService`, `OrderAdminService`, `UserBookAdminService`
- `Admin\UserController`, `Admin\OrderController`, `Admin\UserBookController`, `Admin\DownloadLogController`
- Form Requests: `GrantBookRequest`
- `CleanupCartsCommand`, `ExpirePendingOrdersCommand`

**11.4 — Newsletter backend**
- `NewsletterService` — Resend Audiences API (add/remove contacts) + Resend Broadcasts API (send)
- `NewsletterController` (public subscribe form), `Admin\NewsletterController`
- Form Request: `SendNewsletterRequest`
- Wire `newsletter_consent` on registration → add to Resend Audience

**11.5 — Frontend**
- All admin Blade views: posts, users, orders, user-books, download-logs, newsletter
- Public newsletter subscribe form (single field, no confirm/unsubscribe pages — handled by Resend)

---

## Phase 12 — Hardening & Monitoring

### Schema

No new tables. Index review and additions on existing tables.

Additional indexes to evaluate and add:
- `orders`: INDEX `orders_paid_at_index` (`paid_at`) — for revenue reporting queries
- `download_logs`: INDEX `download_logs_book_id_index` (`book_id`) — for per-book download counts

### Routes

No new routes. Existing routes receive rate limiting and security headers.

### Classes

| Type | Name | Responsibility |
|------|------|----------------|
| Migration | 2026_03_20_000012_add_performance_indexes | Add performance indexes to orders, download_logs |
| Middleware | SecurityHeaders | Apply Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, Referrer-Policy |
| Observer | BookObserver | Invalidate Redis cache for catalog, book detail, homepage on book save/delete |
| Observer | PostObserver | Invalidate Redis cache for blog index on post save/delete |
| Command | OptimizeCoverImagesCommand | Artisan command `app:optimize-covers` to resize and compress cover images |
| Job | OptimizeCoverImage | Queue: resize and compress a single cover image on upload |

---

## Cross-Cutting Concerns

### Shared Traits

| Trait | Provides | Used By |
|-------|----------|---------|
| HasSlug | Auto-generate slug from title via `Str::slug()`, ensure uniqueness by appending suffix | Book, Post, StaticPage |
| HasStatus | Scope `published()` for querying only published records; `isPublished()` helper | Book, Post |
| FormatsPrice | `formattedPrice()` accessor that converts kopecks to ruble display string (e.g. "590 ₽") | Book, OrderItem, Order |

### Base Controller

Laravel's default `App\Http\Controllers\Controller`. No custom base controller needed.

### Service Container Bindings (AppServiceProvider)

| Abstract | Concrete | Notes |
|----------|----------|-------|
| `PaymentProvider` (interface) | `StripePaymentProvider` | Allows future swap to another payment gateway |

### Config Files to Add/Modify

| File | Changes |
|------|---------|
| `config/services.php` | Add Stripe keys (secret, publishable, webhook_secret); add OAuth provider credentials (google, vk, instagram, facebook) |
| `config/filesystems.php` | Add `s3-public` disk (covers) and `s3-private` disk (epubs) |
| `config/bookshop.php` | Custom config: `download_url_ttl` (default 300), `analytics_events` (allowed event names list), `max_books_per_page` |
| `config/cache.php` | Ensure Redis is default store (standard Laravel config) |
| `config/queue.php` | Ensure Redis is default connection (standard Laravel config) |

### Queue Channels and Their Purpose

| Queue Name | Purpose | Priority |
|------------|---------|----------|
| `default` | General purpose jobs | Normal |
| `payments` | ProcessPaymentConfirmation, order status transitions | High |
| `uploads` | ProcessBookFileUpload, OptimizeCoverImage | Low |
| `emails` | SendOrderConfirmationEmail, all queued mail | Normal |
| `analytics` | RecordAnalyticsEvent | Low |

### Cache Key Naming Convention

Pattern: `{entity}:{identifier}:{variant}`

| Key | TTL | Invalidated By |
|-----|-----|----------------|
| `books:published:all` | 1 hour | BookObserver on save/delete |
| `books:featured:all` | 1 hour | BookObserver on save/delete |
| `books:slug:{slug}` | 6 hours | BookObserver on save/delete |
| `posts:published:page:{n}` | 30 min | PostObserver on save/delete |
| `posts:slug:{slug}` | 6 hours | PostObserver on save/delete |
| `static_pages:slug:{slug}` | 24 hours | StaticPageObserver on update |
| `admin:stats:dashboard` | 15 min | OrderObserver on status change |
| `sitemap:xml` | 24 hours | BookObserver, PostObserver on status change |

### S3 Disk Configuration

**s3-public** (covers, post images):
- Bucket: `bookshop-public` (or env-configured)
- Visibility: public
- Path convention: `covers/{book_id}/cover.{ext}`, `posts/{post_id}/cover.{ext}`
- URL: accessed via S3 public URL or CloudFront

**s3-private** (epub files):
- Bucket: `bookshop-private` (or env-configured)
- Visibility: private
- Path convention: `books/{book_id}/book.epub`
- Access: only via pre-signed URLs generated by DownloadService

Both disks use MinIO in local Docker development (same endpoint, different buckets).

---

## Migration Order

Complete ordered list of all migrations across all phases:

| # | Migration Name | Phase | Table |
|---|---------------|-------|-------|
| 1 | 2026_03_20_000001_create_books_table | 2 | books (incl. cover_thumb_path) |
| 2 | 2026_03_20_000002_modify_users_table_add_role_and_newsletter | 3 | users |
| 3 | 2026_03_20_000003_create_oauth_providers_table | 3 | oauth_providers |
| 4 | 2026_03_20_000004_create_cart_items_table | 5 | cart_items |
| 5 | 2026_03_20_000005_create_orders_table | 5 | orders |
| 6 | 2026_03_20_000006_create_order_items_table | 5 | order_items |
| 7 | 2026_03_20_000007_create_user_books_table | 5 | user_books |
| 8 | 2026_03_20_000008_create_download_logs_table | 6 | download_logs |
| 9 | 2026_03_20_000009_create_posts_table | 8 | posts (incl. cover_thumb_path, HTML body) |
| 10 | 2026_03_20_000010_create_analytics_events_table | 10 | analytics_events |
| 11 | 2026_03_20_000011_create_newsletter_subscribers_table | 11 | newsletter_subscribers |
| 12 | 2026_03_20_000012_create_static_pages_table | 11 | static_pages |
| 13 | 2026_03_20_000013_add_performance_indexes | 12 | orders, download_logs, analytics_events |

Note: Laravel's default migrations (users, password_reset_tokens, sessions, cache, jobs, failed_jobs) run before all of the above.

---

## Business Logic Rules (All Phases)

### Phase 2 — Storefront

1. Only books with `status = published` are visible on the public storefront (catalog, homepage carousel, book detail).
2. Featured books (`is_featured = true` AND `status = published`) appear in the homepage carousel, ordered by `sort_order` ASC.
3. Catalog page shows all published books ordered by `sort_order` ASC.
4. Fragment page is publicly accessible regardless of auth status.
5. If a book has no fragment content (`fragment IS NULL`), the fragment page returns 404.
6. Slug must be unique across all books.

### Phase 3 — Authentication

7. Email is mandatory for all users, including OAuth users.
8. If an OAuth provider does not return an email, the user must provide one before account creation completes.
9. If an OAuth callback returns an email that matches an existing user, the provider is linked to that existing account (no duplicate accounts).
10. Password is nullable on users table — OAuth-only users have no password.
11. Email verification is required before checkout (enforced via `verified` middleware).
12. Users with `role = user` are the default. Admin role is set manually in DB or via seeder.
13. EnsureAdmin middleware returns 404 (not 403) to avoid revealing admin panel existence.

### Phase 4 — Admin Books

14. New books default to `draft` status.
15. Books can be toggled between `draft` and `published`.
16. A published book with existing purchases (user_books records) cannot be deleted.
17. A published book with existing purchases cannot be unpublished (set back to draft).
18. Draft books with no purchases can be deleted.
19. Price is entered by admin in rubles (decimal), stored as integer kopecks (multiply by 100).
20. Cover image upload replaces previous cover. Old file is deleted from S3.
21. Epub upload replaces previous epub. Old file is deleted from S3. All existing owners automatically receive the new version.
22. Epub upload is processed via a queued job to avoid HTTP timeout.

### Phase 5 — Cart & Payments

23. A book already owned by the user (exists in user_books) cannot be added to cart.
24. Duplicate cart items (same user+book or same session+book) are prevented by unique constraints.
25. Guest cart is stored by session_id. On login, guest cart items are merged into the user's cart (duplicates are discarded).
26. Checkout requires authentication and email verification.
27. On checkout: an Order (status=pending) and OrderItems are created BEFORE redirecting to Stripe.
28. Each OrderItem captures the price at the time of purchase (snapshot, not a live reference to books.price).
29. The Stripe webhook is the source of truth for payment confirmation — not the success redirect.
30. On `checkout.session.completed` webhook: if order is already `paid`, skip processing (idempotency via stripe_session_id).
31. On successful payment: order status set to `paid`, `paid_at` set, user_books records created for each OrderItem, cart cleared, OrderPaid event dispatched.
32. OrderPaid event triggers queued order confirmation email.
33. Success redirect page: if order is already `paid` (webhook was faster), redirect to `/cabinet/library`; if still `pending`, show polling page that checks `/checkout/status/{order}` every 2 seconds for max 30 seconds.
34. Cancel redirect returns to `/cart` — cart is preserved, order remains `pending` (will expire or be cleaned up).
35. Stripe webhook signature is verified on every request.
36. Allowed order status transitions: pending -> paid, pending -> failed, paid -> refunded.

### Phase 6 — Digital Delivery

37. Download requires authentication and a user_books record for the requested book.
38. Download generates a pre-signed S3 URL with configurable TTL (default 5 minutes from `DOWNLOAD_URL_TTL` env var).
39. Download is rate-limited: max 10 requests per user per book per hour.
40. Every download is logged in download_logs with user_id, book_id, ip_address, and timestamp.
41. If the book has no epub_path, return 404.

### Phase 7 — User Dashboard

42. `/cabinet` redirects to `/cabinet/library`.
43. Library shows books from user_books, each with a download button.
44. Order history is paginated (10 per page), ordered by created_at DESC.
45. OAuth provider unlinking: cannot disconnect the last authentication method if user has no password set.
46. Profile name can be updated. Email is read-only.
47. Password can be changed only if user currently has a password set (password field is not null).

### Phase 8 — Blog

48. Only posts with `status = published` AND `published_at <= now()` are visible on the public blog.
49. Posts are ordered by `published_at` DESC on the blog index.
50. Blog index is paginated (10 per page).
51. Post slug must be unique.
52. Setting `published_at` to a future date effectively schedules the post.

### Phase 9 — SEO

53. Sitemap includes all published books and all published posts (with published_at <= now).
54. Sitemap is regenerated when a book or post status changes (via model observer).
55. robots.txt disallows `/admin`, `/cabinet`, `/checkout`.
56. All public pages include meta title, description, and canonical URL.
57. Book detail pages include JSON-LD Product structured data and OpenGraph tags.
58. Blog post pages include OpenGraph article tags.

### Phase 10 — Analytics

59. Analytics uses Google Analytics 4 — implemented as part of Phase 11.5, no separate phase.
60. GA Measurement ID stored in `.env` as `GOOGLE_ANALYTICS_ID`; script omitted when value is empty.
61. Standard GA4 events: `add_to_cart`, `begin_checkout`, `purchase`, `file_download`.
62. Page views, sessions, funnels provided by GA4 out of the box — no custom aggregation needed.
63. GA4 account and Measurement ID configured at production deploy time (domain required).

### Phase 11 — Admin Blog, Users & Orders

64. Blog posts can be deleted from admin. There is no restriction on deleting published posts.
65. Blog post body is sanitized via HtmlSanitizerService (HtmlPurifier) on every store/update in PostAdminService.
66. Banned users (`banned_at IS NOT NULL`) are blocked on every authenticated request via `CheckNotBanned` middleware — response is 403.
67. Admin cannot ban themselves.
68. Refund flow is manual: admin processes refund in Stripe panel, then marks the order as `Refunded` in admin UI. This triggers revocation of all associated `user_books` in a single DB transaction.
69. `user_books` revocation is soft — `revoked_at` timestamp is set, record is not deleted. Download endpoint checks `revoked_at IS NULL`.
70. Manual book grant creates a `user_books` record with `order_id = null`.
71. Newsletter subscription uses double opt-in: `confirmed_at = null` until token link is clicked.
72. Unsubscribe link (with token) is included in every newsletter email footer.
73. Newsletter sending is batched via queue — no memory overflow on large subscriber lists.
74. Cart cleanup: guest cart items (`user_id IS NULL`) older than 7 days deleted by `app:cleanup-carts` (scheduled daily).
75. Pending orders older than 1 hour are marked `failed` by `app:expire-pending-orders` (scheduled every 15 minutes).

### Phase 12 — Hardening

69. Rate limiting applied to: auth routes (throttle:login), download endpoint (throttle:download), analytics endpoint (throttle:analytics), checkout (throttle:checkout).
70. Security headers applied globally via SecurityHeaders middleware.
71. Redis cache invalidated via model observers — not TTL-only.
72. Cover images are optimized on upload (resize to max dimensions, compress) via queued job.
73. Database backups run daily via `spatie/laravel-backup` scheduled command.
74. Horizon is used in production for queue monitoring.
75. Telescope is enabled in local/dev environments only.

---

## All Clarifying Questions — Resolved

All 10 architect questions have been answered and incorporated into the blueprint above. No open architectural questions remain.

| # | Question | Answer |
|---|----------|--------|
| 1 | Refund flow | Stripe Dashboard only — no admin route |
| 2 | Newsletter | Own logic + SMTP; `newsletter_subscribers` table; double opt-in |
| 3 | Blog post cover | Optional (nullable) |
| 4 | OAuth email form | Inline on callback — no separate route |
| 5 | Static pages | Fixed set, admin edits only |
| 6 | Admin orders | Yes — `GET /admin/orders`, `GET /admin/orders/{order}` |
| 7 | Cart cleanup | `app:cleanup-carts` scheduled daily (guest items > 7 days) |
| 8 | Pending orders | `app:expire-pending-orders` every 15 min; > 1 hour → `failed` |
| 9 | Cover sizes | Two columns: `cover_path` (full) + `cover_thumb_path` (thumbnail) |
| 10 | Blog body format | HTML sanitized via HtmlPurifier on save |

---

*End of architecture blueprint. This document is the authoritative reference for all implementation phases.*
