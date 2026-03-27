<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
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

// Temporary design system preview — remove before Phase 3
Route::get('/design-preview', function () {
    return view('design-preview');
});
