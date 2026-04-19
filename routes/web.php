<?php

declare(strict_types=1);

use App\Features\Admin\Controllers\BookController as AdminBookController;
use App\Features\Admin\Controllers\DashboardController;
use App\Features\Admin\Controllers\DownloadLogController as AdminDownloadLogController;
use App\Features\Admin\Controllers\NewsletterController as AdminNewsletterController;
use App\Features\Admin\Controllers\OrderController as AdminOrderController;
use App\Features\Admin\Controllers\PostController as AdminPostController;
use App\Features\Admin\Controllers\UserBookController as AdminUserBookController;
use App\Features\Admin\Controllers\UserController as AdminUserController;
use App\Features\AgeVerification\Controllers\AgeVerificationController;
use App\Features\Auth\Controllers\OAuthController;
use App\Features\Blog\Controllers\PostController;
use App\Features\Cabinet\Controllers\CabinetController;
use App\Features\Cabinet\Controllers\SettingsController;
use App\Features\Cart\Controllers\CartController;
use App\Features\Catalog\Controllers\BookController;
use App\Features\Catalog\Controllers\HomeController;
use App\Features\Checkout\Controllers\CheckoutController;
use App\Features\Checkout\Controllers\WebhookController;
use App\Features\Download\Controllers\DownloadController;
use App\Features\Pages\Controllers\SitemapController;
use App\Features\Pages\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Blog
Route::get('/blog', [PostController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [PostController::class, 'show'])->name('blog.show');

Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{book:slug}', [BookController::class, 'show'])->name('books.show');
Route::get('/books/{book:slug}/fragment', [BookController::class, 'fragment'])->name('books.fragment');

$staticPages = [
    'about',
    'privacy',
    'terms',
    'offer',
    'personal-data',
    'newsletter-consent',
    'cookies',
    'refund',
    'contacts',
    'payment-info',
];

foreach ($staticPages as $page) {
    Route::get("/{$page}", [StaticPageController::class, 'show'])
        ->name("static.{$page}")
        ->defaults('page', $page);
}

// Cart routes (guests and authenticated users)
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/{book}', [CartController::class, 'store'])->name('cart.store');
Route::delete('/cart/{book}', [CartController::class, 'destroy'])->name('cart.destroy');

// OAuth redirect and callback — no guest middleware so authenticated users can link providers.
// Complete-registration routes remain guest-only (they are part of the initial signup flow).
Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])
    ->name('auth.oauth.redirect');
Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])
    ->name('auth.oauth.callback');

Route::middleware('guest')->group(function () {
    Route::get('/auth/complete-registration', [OAuthController::class, 'showCompleteRegistration'])
        ->name('auth.complete-registration');
    Route::post('/auth/complete-registration', [OAuthController::class, 'completeRegistration'])
        ->name('auth.complete-registration.store');
});

// Age verification (no auth required — guests use session, authenticated users persist to DB)
Route::post('/age-verification', [AgeVerificationController::class, 'store'])
    ->name('age-verification.store');

// Checkout routes (auth + verified)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/status/{order}', [CheckoutController::class, 'status'])->name('checkout.status');
});

// Payment provider webhooks — no CSRF, no auth middleware (Rule 35).
// Signature verification is performed inside each provider (Rule 35).
Route::post('/webhooks/{provider}', [WebhookController::class, 'handle'])->name('webhooks.handle');

// Download (auth + verified + rate limited)
Route::get('/books/{book:slug}/download', [DownloadController::class, 'show'])
    ->middleware(['auth', 'verified', 'throttle:download'])
    ->name('books.download');

// User cabinet (auth + verified)
Route::middleware(['auth', 'verified'])->prefix('cabinet')->name('cabinet.')->group(function () {
    Route::get('/', [CabinetController::class, 'index'])->name('index');
    Route::get('/library', [CabinetController::class, 'library'])->name('library');
    Route::get('/orders', [CabinetController::class, 'orders'])->name('orders');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/newsletter', [SettingsController::class, 'toggleNewsletter'])->name('settings.newsletter');
    Route::post('/settings/oauth/{provider}', [SettingsController::class, 'linkProvider'])->name('settings.oauth.link');
    Route::delete('/settings/oauth/{provider}', [SettingsController::class, 'unlinkProvider'])->middleware('password.confirm')->name('settings.oauth.unlink');
});

// Admin panel (auth + verified + admin role required)
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/books', [AdminBookController::class, 'index'])->name('books.index');
    Route::get('/books/create', [AdminBookController::class, 'create'])->name('books.create');
    Route::post('/books', [AdminBookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [AdminBookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [AdminBookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [AdminBookController::class, 'destroy'])->name('books.destroy');
    Route::patch('/books/{book}/toggle-status', [AdminBookController::class, 'toggleStatus'])->name('books.toggle-status');
    Route::patch('/books/{book}/toggle-featured', [AdminBookController::class, 'toggleFeatured'])->name('books.toggle-featured');
    Route::patch('/books/{book}/toggle-availability', [AdminBookController::class, 'toggleAvailability'])->name('books.toggle-availability');

    Route::get('/posts', [AdminPostController::class, 'index'])->name('posts.index');
    Route::get('/posts/create', [AdminPostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [AdminPostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [AdminPostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{post}', [AdminPostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [AdminPostController::class, 'destroy'])->name('posts.destroy');
    Route::patch('/posts/{post}/toggle-status', [AdminPostController::class, 'toggleStatus'])->name('posts.toggle-status');

    // Users
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}/ban', [AdminUserController::class, 'ban'])->name('users.ban');
    Route::patch('/users/{user}/unban', [AdminUserController::class, 'unban'])->name('users.unban');
    Route::post('/users/{user}/send-password-reset', [AdminUserController::class, 'sendPasswordReset'])->name('users.send-password-reset');
    Route::post('/users/{user}/verify-email', [AdminUserController::class, 'verifyEmail'])->name('users.verify-email');
    Route::post('/users/{user}/grant-book', [AdminUserBookController::class, 'grant'])->name('users.grant-book');

    // Orders
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');

    // UserBooks
    Route::patch('/user-books/{userBook}/revoke', [AdminUserBookController::class, 'revoke'])->name('user-books.revoke');
    Route::patch('/user-books/{userBook}/restore', [AdminUserBookController::class, 'restore'])->name('user-books.restore');

    // Download logs
    Route::get('/download-logs', [AdminDownloadLogController::class, 'index'])->name('download-logs.index');

    // Newsletter
    Route::get('/newsletter', [AdminNewsletterController::class, 'index'])->name('newsletter.index');
    Route::post('/newsletter/send', [AdminNewsletterController::class, 'send'])->name('newsletter.send');
});

require __DIR__.'/auth.php';
