<?php

declare(strict_types=1);

namespace App\Features\Admin\Services;

use App\Models\Book;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookCoverService
{
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

    public function deleteCover(Book $book): void
    {
        if ($book->cover_path) {
            Storage::disk('s3-public')->delete($book->cover_path);
        }

        if ($book->cover_thumb_path) {
            Storage::disk('s3-public')->delete($book->cover_thumb_path);
        }
    }
}
