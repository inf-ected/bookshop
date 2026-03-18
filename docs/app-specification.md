# App Specification — Digital Bookshop

**Project**: Single-author digital book store
**Stack**: Laravel 12, PHP 8.4, MySQL, Redis, Docker, S3
**Language**: Russian only
**Last updated**: 2026-03-18

---

## 1. Product Overview

A minimalist digital bookshop for a single Russian-language author. The author sells epub books directly to readers. The catalog is small (3–5 books) and fixed. The product focuses on clean reading experience, frictionless purchase, and reliable epub delivery.

**Primary user**: Russian-speaking reader who discovers the author's work and wants to purchase and download epub files.

**Secondary user**: The author — manages books, blog, and content via admin panel without developer involvement.

---

## 2. Design Principles

- **Mobile-first**: all layouts designed at 375px first, then scaled up
- **Minimalist**: clean whitespace, limited color palette, no decorative elements
- **Readable**: book content (annotations, fragments) optimized for reading comfort
- **Fast**: no heavy JS frameworks; Blade + Alpine.js + Tailwind only
- **No i18n**: Russian only, hardcoded strings in Blade views

### UI Components (global)
- Burger menu (mobile) / horizontal nav (desktop): catalog, login/register, cabinet link if authenticated
- Footer: social media icons (VK, Instagram, Facebook, Telegram) + legal page links
- Book card: cover image, title, short preview (optional), "В корзину" button

---

## 3. Pages & Routes

### Public (unauthenticated)

| Route | Page | Description |
|-------|------|-------------|
| `/` | Homepage | Horizontal book carousel + brief author intro |
| `/books` | Catalog | Grid of all published books |
| `/books/{slug}` | Book Detail | Full book info, buy CTA, fragment link |
| `/books/{slug}/fragment` | Sample Fragment | Read-only fragment with pagination |
| `/blog` | Blog index | Paginated list of published posts |
| `/blog/{slug}` | Blog post | Full post content |
| `/about` | О нас | Static page |
| `/privacy` | Политика конфиденциальности | Static page |
| `/terms` | Пользовательское соглашение | Static page |
| `/offer` | Публичная оферта | Static page |
| `/personal-data` | Политика обработки персональных данных | Static page |
| `/newsletter-consent` | Согласие на получение рассылки | Static page |
| `/cookies` | Политика использования cookies | Static page |
| `/refund` | Политика возврата | Static page |
| `/contacts` | Контакты | Static page |
| `/payment-info` | Оплата и доставка | Static page |

### Auth

| Route | Page |
|-------|------|
| `/register` | Registration form |
| `/login` | Login form |
| `/forgot-password` | Password reset request |
| `/reset-password/{token}` | Set new password |
| `/auth/{provider}/redirect` | OAuth redirect |
| `/auth/{provider}/callback` | OAuth callback |
| `/email/verify` | Email verification notice |
| `/email/verify/{id}/{hash}` | Email verification link |

### Authenticated (requires verified email)

| Route | Page |
|-------|------|
| `/cart` | Cart |
| `/checkout` | Checkout / Stripe redirect |
| `/checkout/success` | Post-payment success handler |
| `/cabinet` | Dashboard (redirect to /cabinet/library) |
| `/cabinet/library` | My books |
| `/cabinet/orders` | Order history |
| `/cabinet/settings` | Profile, connected OAuth providers |
| `/books/{book}/download` | epub download (proxied signed URL) |

### Admin (role = admin)

| Route | Page |
|-------|------|
| `/admin` | Dashboard: stats widget |
| `/admin/books` | Book list |
| `/admin/books/create` | Create book |
| `/admin/books/{id}/edit` | Edit book |
| `/admin/posts` | Blog post list |
| `/admin/posts/create` | Create post |
| `/admin/posts/{id}/edit` | Edit post |
| `/admin/pages` | Static page editor |
| `/admin/analytics` | Analytics dashboard |

### System

| Route | Purpose |
|-------|---------|
| `/webhooks/stripe` | Stripe webhook receiver |
| `/analytics/event` | Internal event tracker (POST) |
| `/up` | Health check |

---

## 4. Page Specifications

### 4.1 Homepage (`/`)

**Layout (mobile-first)**:
- Header: logo left, burger menu right
- Hero section: author name / tagline (static, short)
- Horizontal scroll carousel: 3–5 book cards
- Footer

**Book Card in Carousel**:
- Cover image (aspect ratio 2:3, lazy-loaded)
- Title (1–2 lines, truncated)
- Short preview text (optional, max 2 lines, truncated)
- "В корзину" button — if book already purchased, show "В библиотеке" (disabled)

**Carousel behavior**:
- CSS `scroll-snap` + `overflow-x: auto` — no JS carousel library
- Swipe on mobile, drag on desktop
- Dots indicator (Alpine.js state)

---

### 4.2 Book Catalog (`/books`)

- Grid layout: 1 column mobile, 2 columns tablet, 3 columns desktop
- Same book card component as carousel
- No pagination (≤5 books)
- Empty state if no published books

---

### 4.3 Book Detail (`/books/{slug}`)

**Sections (in order)**:
1. Cover image (prominent, above fold on mobile)
2. Title (H1)
3. Price (formatted, e.g. "590 ₽")
4. CTA buttons:
   - "В корзину" (if not in cart and not purchased)
   - "Уже в корзине → Перейти в корзину" (if in cart)
   - "В библиотеке → Скачать epub" (if purchased)
5. Short annotation (plain text, a few sentences — independent from fragment)
6. Excerpt / longer preview (rich text or plain, scrollable section)
7. "Читать ознакомительный фрагмент →" link (if fragment exists)

**Fields stored separately**:
- `annotation`: short marketing description (1–3 sentences)
- `excerpt`: longer preview / back-cover style text
- `fragment`: full sample fragment text (for the fragment page)
- These three fields are independent and all managed in admin

---

### 4.4 Sample Fragment Page (`/books/{slug}/fragment`)

**Purpose**: Let readers sample a chapter before buying.

**Requirements**:
- No text selection (CSS `user-select: none` + `pointer-events: none` on text container)
- No right-click context menu on text (Alpine.js `@contextmenu.prevent`)
- No keyboard copy shortcut blocking (JS `keydown` listener blocking Ctrl+C / Cmd+C) — note: this is a UX deterrent, not a security mechanism; determined users can always view source
- Pagination: split fragment into pages client-side (Alpine.js) based on character count or line count — configurable
- "Страница X из N" indicator
- Last page shows end-cap: grey divider + "— Конец ознакомительного фрагмента —" + "Купить полную книгу" CTA button
- No auth required — fragment is publicly accessible

---

### 4.5 Cart (`/cart`)

**State**:
- Session-based for guests
- Database-backed for authenticated users
- Merge guest cart into user cart on login

**Layout**:
- List of cart items: cover (small), title, price, "Удалить" button
- Order total
- "Оформить заказ" button
  - If not authenticated: show info block "Для оплаты необходимо войти или зарегистрироваться" + Login / Register buttons (redirect back to cart after auth)
  - If authenticated but email not verified: show verification reminder
  - If authenticated and verified: proceed to checkout
- Empty cart state: illustration/message + "Перейти в каталог" link
- Books already owned are not shown in cart (prevent re-purchase)

---

### 4.6 Checkout Flow

1. User clicks "Оформить заказ" from cart
2. Server creates Stripe Checkout Session with line items matching cart
3. Server creates `Order` (status: `pending`) + `OrderItems`
4. Redirect to Stripe hosted checkout page
5. **Success path**: Stripe redirects to `/checkout/success?session_id=...`
   - If order already `paid` (webhook beat the redirect): redirect to `/cabinet/library` with flash "Книги добавлены в библиотеку"
   - If order still `pending`: show "Обрабатываем платёж..." with auto-refresh (poll `/checkout/status/{order}` every 2s, max 30s)
6. **Cancel path**: Stripe redirects to `/cart` — cart is preserved, order is abandoned
7. **Webhook** (`checkout.session.completed`):
   - Verify Stripe signature
   - Find order by `stripe_session_id`
   - If already `paid`: skip (idempotency)
   - Mark order `paid`, set `paid_at`
   - Create `user_book` records for each purchased book
   - Queue order confirmation email

---

### 4.7 User Cabinet

**`/cabinet/library`**:
- Grid of purchased books (same card component, smaller)
- Each card: cover, title, "Скачать epub" button
- Click "Скачать epub" → `GET /books/{book}/download` → returns signed S3 URL → browser downloads file
- Empty state: "Ваша библиотека пуста. Перейти в каталог →"

**`/cabinet/orders`**:
- Table/list: date, book titles, total amount, status badge (Оплачен / Обрабатывается / Отменён)
- Paginated (10 per page)

**`/cabinet/settings`**:
- Display name, email (read-only if set via OAuth without password)
- Password change (if user has a password)
- Connected OAuth providers: list with "Подключить" / "Отключить" buttons
  - Cannot disconnect last authentication method (must have password or at least one provider)
- Newsletter subscription toggle

---

### 4.8 Admin Panel

**Dashboard (`/admin`)**:
- Stats widget: total revenue (all time / this month), orders count, books sold, downloads count
- Quick links to all sections

**Book List (`/admin/books`)**:
- Table: cover (thumbnail), title, status badge, featured toggle, sort order, actions (Edit, Delete)
- Delete only allowed for `draft` books with no purchases
- Status badge clickable: toggle draft ↔ published
- Featured toggle: checkbox

**Book Create/Edit (`/admin/books/{id}/edit`)**:

| Field | Type | Notes |
|-------|------|-------|
| Title | text | Required |
| Slug | text | Auto-generated, editable |
| Status | select | draft / published |
| Featured | checkbox | Appears in carousel |
| Sort order | number | Lower = first |
| Price | number | In roubles (admin input); stored as kopecks (×100) |
| Cover image | file upload | jpg/png/webp, stored on public S3 bucket |
| Annotation | textarea | Short marketing text (book detail page) |
| Excerpt | textarea | Longer preview text (book detail page) |
| Fragment | textarea (large) | Sample fragment text (fragment page) |
| epub file | file upload | Stored on private S3 bucket |

**Blog Post Create/Edit**:

| Field | Type |
|-------|------|
| Title | text |
| Slug | text (auto) |
| Status | draft / published |
| Published at | datetime (future = scheduled) |
| Excerpt | textarea |
| Cover image | file upload |
| Body | textarea |

---

## 5. Authentication & OAuth

### Registration Flow

1. User opens `/register`
2. Fills: name, email, password, password confirmation
3. Accepts Terms of Service + Privacy Policy (checkbox, required)
4. Optionally checks newsletter consent
5. On submit: account created, verification email sent
6. Redirect to `/email/verify` notice page
7. User clicks email link → account verified → redirect to `/cabinet/library`

### OAuth Flow

1. User clicks "Войти через [Provider]" on login or register page
2. Redirect to provider
3. Callback received
4. **Case A — new user, provider returns email**:
   - Email not in DB → create account, link provider, send verification email, go to step 7 above
   - Email in DB → link provider to existing account, log in
5. **Case B — new user, provider does NOT return email**:
   - Show form: "Укажите ваш email для завершения регистрации" (required)
   - On submit: same as Case A with provided email
6. **Case C — returning user**:
   - `oauth_providers` record found → log in directly
7. After login: redirect to originally intended page or `/cabinet/library`

### Provider Linking (`/cabinet/settings`)

- "Подключить Google / VK / Instagram / Facebook" — OAuth flow, on callback link `oauth_providers` record to current user
- "Отключить" — removes `oauth_providers` record
- Guard: if disconnecting the last auth method and no password is set → show error "Установите пароль перед отключением провайдера"

---

## 6. Email Notifications

| Trigger | Email |
|---------|-------|
| Registration | Email verification link |
| Password reset | Reset link |
| Successful payment | Order confirmation with list of purchased books |
| (Future) Newsletter | Managed separately |

All transactional emails: queued via Redis, sent via configured SMTP/API provider (Postmark recommended).

---

## 7. Analytics Events

| Event | Trigger | Payload |
|-------|---------|---------|
| `page_view` | Every public page load | page, referrer, device type |
| `book_add_to_cart` | "В корзину" click | book_id, source (catalog/detail/carousel) |
| `checkout_started` | "Оформить заказ" click | order total, book count |
| `checkout_completed` | Webhook: order paid | order_id, total |
| `fragment_started` | Fragment page load | book_id |
| `fragment_completed` | Last fragment page reached | book_id |
| `fragment_cta_clicked` | "Купить" on fragment end-cap | book_id |
| `download_initiated` | Download endpoint called | book_id |

Custom events sent to `POST /analytics/event` (internal endpoint) in addition to any self-hosted analytics script.

---

## 8. Static Pages — Recommended Content Outline

| Page | Purpose | Key content |
|------|---------|-------------|
| О нас | Author introduction | Author bio, mission, contact email |
| Публичная оферта | Legal contract for purchase | Parties, subject matter, price, delivery (epub), acceptance |
| Пользовательское соглашение | General terms | Account rules, prohibited actions, liability |
| Политика конфиденциальности | GDPR/152-ФЗ compliance | What data is collected, why, how long |
| Политика обработки персональных данных | 152-ФЗ requirement | Detailed personal data processing description |
| Согласие на получение рассылки | Newsletter opt-in proof | Consent text referenced during registration |
| Политика использования cookies | ePrivacy compliance | Cookies used, purpose, opt-out |
| Политика возврата | Consumer protection | No refunds on digital goods after download; exception process |
| Оплата и доставка | User guidance | Payment via Stripe, delivery = epub download link in cabinet |
| Контакты | Support | Support email, expected response time |

> **Note for lawyer review**: Публичная оферта, Политика обработки персональных данных, and Пользовательское соглашение require legal review before going live. The epub sale constitutes a contract under Russian civil law (ГК РФ ст. 435–438).

---

## 9. Technical Constraints Summary

| Constraint | Value |
|------------|-------|
| Backend | Laravel 12, PHP 8.4 |
| Frontend | Blade + Alpine.js + Tailwind CSS |
| Database | MySQL 8 |
| Cache / Queue | Redis |
| File storage | AWS S3 (prod), MinIO (dev) |
| Book format | epub only |
| Payment | Stripe Checkout |
| OAuth providers | Google, VK, Instagram, Facebook |
| Language | Russian only |
| Max books in catalog | 3–5 |
| Download TTL | 5 minutes (configurable) |
| Price storage | Integer (kopecks) |
| Currency | RUB (stored as `currency char(3)` for future) |

---

## 10. Out of Scope (intentionally excluded)

- Multiple languages / i18n
- Cart for physical goods
- Free books / giveaways
- PDF / fb2 formats (epub only)
- Comments on books or blog posts
- Customer reviews / ratings
- Subscription / membership model
- Multiple authors
- Affiliate / referral system
- Mobile app

---

*End of specification. This document describes the product as agreed. Changes require an update here before implementation begins.*
