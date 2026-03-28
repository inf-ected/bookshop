<?php

declare(strict_types=1);

use App\Http\Controllers\BookController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StaticPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
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
