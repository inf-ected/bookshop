<?php

declare(strict_types=1);

namespace App\Features\Download\Services;

use App\Models\Book;
use App\Models\DownloadLog;
use App\Models\User;

class DownloadService
{
    public function generateUrl(Book $book): string
    {
        // TODO Phase 13.4: replace with BookFile-based URL generation.
        // epub_path column was dropped in Phase 13.1.
        throw new \LogicException('DownloadService::generateUrl() requires Phase 13.4 BookFile-based implementation.');
    }

    public function logDownload(User $user, Book $book, string $ipAddress): void
    {
        DownloadLog::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'ip_address' => $ipAddress,
            'format' => 'epub', // TODO Phase 13.4: pass actual BookFileFormat
            'downloaded_at' => now(),
        ]);
    }
}
