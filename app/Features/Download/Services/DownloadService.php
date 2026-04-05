<?php

declare(strict_types=1);

namespace App\Features\Download\Services;

use App\Models\Book;
use App\Models\DownloadLog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DownloadService
{
    public function generateUrl(Book $book): string
    {
        // Use the presign disk so the URL is signed with the public-facing endpoint.
        // In local dev S3_TEMPORARY_URL_BASE=http://localhost:9000 overrides the
        // internal Docker endpoint (minio:9000), making the link resolvable by browsers.
        return Storage::disk('s3-private-presign')->temporaryUrl(
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
