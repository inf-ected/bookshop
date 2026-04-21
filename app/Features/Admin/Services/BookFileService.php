<?php

declare(strict_types=1);

namespace App\Features\Admin\Services;

use App\Models\Book;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookFileService
{
    /**
     * Upload cover image (full size) to the public S3 bucket.
     * Deletes the old cover if one exists.
     * Returns the stored path.
     */
    public function uploadCover(Book $book, UploadedFile $file): string
    {
        if ($book->cover_path) {
            Storage::disk('s3-public')->delete($book->cover_path);
        }

        $path = 'covers/'.Str::uuid().'.'.$file->getClientOriginalExtension();
        $result = Storage::disk('s3-public')->put($path, $file->getContent(), 'public');

        if ($result === false) {
            throw new \RuntimeException('Failed to upload file to S3.');
        }

        return $path;
    }

    /**
     * Upload cover thumbnail to the public S3 bucket.
     * Deletes the old thumbnail if one exists.
     * Returns the stored path.
     */
    public function uploadCoverThumb(Book $book, UploadedFile $file): string
    {
        if ($book->cover_thumb_path) {
            Storage::disk('s3-public')->delete($book->cover_thumb_path);
        }

        $path = 'covers/thumbs/'.Str::uuid().'.'.$file->getClientOriginalExtension();
        $result = Storage::disk('s3-public')->put($path, $file->getContent(), 'public');

        if ($result === false) {
            throw new \RuntimeException('Failed to upload file to S3.');
        }

        return $path;
    }

    /**
     * Delete the cover files from public S3 bucket.
     */
    public function deleteCover(Book $book): void
    {
        if ($book->cover_path) {
            Storage::disk('s3-public')->delete($book->cover_path);
        }

        if ($book->cover_thumb_path) {
            Storage::disk('s3-public')->delete($book->cover_thumb_path);
        }
    }

    /**
     * Delete all book files from private S3 bucket.
     *
     * TODO Phase 13.3: iterate $book->files and delete each S3 path.
     * epub_path column was dropped in Phase 13.1.
     */
    public function deleteEpub(Book $book): void
    {
        // No-op until Phase 13.3 adds deleteBookFiles() with BookFile iteration.
    }
}
