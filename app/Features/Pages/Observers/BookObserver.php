<?php

declare(strict_types=1);

namespace App\Features\Pages\Observers;

use App\Enums\BookStatus;
use App\Models\Book;
use Illuminate\Support\Facades\Cache;

class BookObserver
{
    public function created(Book $book): void
    {
        if ($book->status === BookStatus::Published) {
            Cache::forget('sitemap.xml');
        }
    }

    public function updated(Book $book): void
    {
        if ($book->wasChanged('status', 'slug')) {
            Cache::forget('sitemap.xml');
        }
    }

    public function deleted(Book $book): void
    {
        Cache::forget('sitemap.xml');
    }
}
