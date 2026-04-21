<?php

declare(strict_types=1);

namespace App\Features\Admin\Services;

use App\Enums\BookFileFormat;
use App\Enums\BookFileStatus;
use App\Features\Admin\Jobs\ConvertBookFormat;
use App\Features\Admin\Jobs\UploadSourceFile;
use App\Models\Book;
use App\Models\BookFile;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookFileService
{
    /**
     * Upload a derived format file directly to S3 and mark it ready.
     * No conversion is triggered (Rule 7).
     */
    public function uploadDerived(Book $book, UploadedFile $file, BookFileFormat $format): void
    {
        $s3Path = $this->streamToS3($file->getRealPath(), $book->id, $format);

        $existing = $this->findBookFile($book, $format, isSource: false);

        if ($existing instanceof BookFile) {
            if ($existing->path !== null) {
                Storage::disk('s3-private')->delete($existing->path);
            }

            $existing->update([
                'path' => $s3Path,
                'status' => BookFileStatus::Ready,
                'error_message' => null,
                'is_source' => false,
            ]);
        } else {
            BookFile::create([
                'book_id' => $book->id,
                'format' => $format,
                'status' => BookFileStatus::Ready,
                'path' => $s3Path,
                'is_source' => false,
            ]);
        }
    }

    /**
     * Move the uploaded source file to a temp path and dispatch UploadSourceFile.
     * Used by both BookFileController and BookAdminService (Rule 4).
     */
    public function queueSourceUpload(Book $book, UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $format = BookFileFormat::from($ext);

        $tempPath = $this->moveToTemp($file, $ext);
        $bookFile = $this->upsertSourceBookFile($book, $format);

        UploadSourceFile::dispatch($bookFile->id, $tempPath);
    }

    /**
     * Reset a failed BookFile to pending and re-dispatch ConvertBookFormat (Rule 11).
     *
     * @throws HttpResponseException
     */
    public function retryConversion(Book $book, BookFile $bookFile): void
    {
        if ($bookFile->status !== BookFileStatus::Failed) {
            abort(422, 'Повторная попытка возможна только для файлов со статусом «Ошибка».');
        }

        $source = BookFile::query()
            ->where('book_id', $book->id)
            ->where('is_source', true)
            ->where('status', BookFileStatus::Ready)
            ->first();

        if (! $source instanceof BookFile) {
            abort(422, 'Исходный файл не найден или ещё не готов.');
        }

        $bookFile->update([
            'status' => BookFileStatus::Pending,
            'error_message' => null,
        ]);

        ConvertBookFormat::dispatch($bookFile->id, $source->id);
    }

    /**
     * Delete all format files from S3 private disk for the given book.
     * Does not delete DB records — cascade FK handles that on book delete.
     */
    public function deleteAll(Book $book): void
    {
        $book->files->each(function (BookFile $bookFile): void {
            if ($bookFile->path !== null) {
                Storage::disk('s3-private')->delete($bookFile->path);
            }
        });
    }

    private function streamToS3(string $localPath, int $bookId, BookFileFormat $format): string
    {
        $s3Path = "books/{$bookId}/".Str::uuid().'.'.$format->extension();

        $handle = fopen($localPath, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for reading: {$localPath}");
        }

        Storage::disk('s3-private')->put($s3Path, $handle);
        fclose($handle);

        return $s3Path;
    }

    private function upsertSourceBookFile(Book $book, BookFileFormat $format): BookFile
    {
        $existing = $this->findBookFile($book, $format, isSource: true);

        if ($existing instanceof BookFile) {
            if ($existing->path !== null) {
                Storage::disk('s3-private')->delete($existing->path);
            }

            $existing->update([
                'status' => BookFileStatus::Pending,
                'path' => null,
                'error_message' => null,
            ]);

            return $existing;
        }

        return BookFile::create([
            'book_id' => $book->id,
            'format' => $format,
            'status' => BookFileStatus::Pending,
            'is_source' => true,
        ]);
    }

    private function findBookFile(Book $book, BookFileFormat $format, bool $isSource): ?BookFile
    {
        return BookFile::query()
            ->where('book_id', $book->id)
            ->where('format', $format)
            ->where('is_source', $isSource)
            ->first();
    }

    private function moveToTemp(UploadedFile $file, string $ext): string
    {
        $basePath = tempnam(sys_get_temp_dir(), 'bookshop_source_');
        $tempPath = $basePath.'.'.$ext;
        $file->move(dirname($tempPath), basename($tempPath));
        @unlink($basePath);

        return $tempPath;
    }
}
