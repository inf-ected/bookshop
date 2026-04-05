<?php

declare(strict_types=1);

namespace App\Features\Pages\Observers;

use App\Models\Book;
use Illuminate\Support\Facades\Cache;

class BookObserver
{
    public function updated(Book $book): void
    {
        if ($book->wasChanged('status')) {
            Cache::forget('sitemap.xml');
        }
    }
}
