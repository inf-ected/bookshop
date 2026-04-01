<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Book;
use App\Models\DownloadLog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DownloadService
{
    public function generateUrl(Book $book): string
    {
        return Storage::disk('s3-private')->temporaryUrl(
            $book->epub_path,
            now()->addSeconds(config('bookshop.download_url_ttl', 300)),
        );
    }

    public function logDownload(User $user, Book $book, string $ipAddress): void
    {
        DownloadLog::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'ip_address' => $ipAddress,
            'downloaded_at' => now(),
        ]);
    }
}
