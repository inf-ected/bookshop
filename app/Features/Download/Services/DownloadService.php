<?php

declare(strict_types=1);

namespace App\Features\Download\Services;

use App\Models\BookFile;
use App\Models\DownloadLog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DownloadService
{
    /**
     * Generate a pre-signed S3 URL for the given BookFile.
     *
     * Service gate (Rule 3): DOCX is never delivered to clients.
     *
     * @throws \InvalidArgumentException if the format is not client-accessible
     */
    public function generateUrl(BookFile $bookFile): string
    {
        if (! $bookFile->format->isClientAccessible()) {
            throw new \InvalidArgumentException('DOCX format is not available for client download.');
        }

        $clientFilename = $bookFile->book->slug . '.' . $bookFile->format->extension();

        return Storage::disk('s3-private-presign')->temporaryUrl(
            $bookFile->path,
            now()->addSeconds(config('bookshop.download_url_ttl', 300)),
            ['ResponseContentDisposition' => 'attachment; filename="'.$clientFilename.'"'],
        );
    }

    public function logDownload(User $user, BookFile $bookFile, string $ipAddress): void
    {
        DownloadLog::create([
            'user_id' => $user->id,
            'book_id' => $bookFile->book_id,
            'ip_address' => $ipAddress,
            'format' => $bookFile->format,
            'downloaded_at' => now(),
        ]);
    }
}
