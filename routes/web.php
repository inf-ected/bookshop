<?php

declare(strict_types=1);

use App\Features\Admin\Controllers\BookController as AdminBookController;
use App\Features\Admin\Controllers\DashboardController;
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
use App\Features\Pages\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

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

// Checkout routes (auth + verified)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/status/{order}', [CheckoutController::class, 'status'])->name('checkout.status');
});

// Stripe webhook — no CSRF, no auth middleware (Rule 35)
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe'])->name('webhooks.stripe');

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
    Route::post('/settings/oauth/{provider}', [SettingsController::class, 'linkProvider'])->name('settings.oauth.link');
    Route::delete('/settings/oauth/{provider}', [SettingsController::class, 'unlinkProvider'])->name('settings.oauth.unlink');
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
});

require __DIR__.'/auth.php';
