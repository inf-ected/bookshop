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
use RuntimeException;

class BookFileService
{
    /**
     * Upload a derived format file directly to S3 and mark it ready.
     * No conversion is triggered (Rule 7).
     */
    public function uploadDerived(Book $book, UploadedFile $file, BookFileFormat $format): void
    {
        $s3Path = $this->streamToS3($file, "books/{$book->id}/derived.{$format->extension()}");

        $this->upsertBookFile($book, $format, isSource: false, attributes: [
            'path' => $s3Path,
            'status' => BookFileStatus::Ready,
            'error_message' => null,
            'is_source' => false,
        ]);
    }

    /**
     * Move the uploaded source file to a temp path and dispatch UploadSourceFile.
     * Used by both BookFileController and BookAdminService (Rule 4).
     *
     * Rule 8: re-uploading source resets all derived files. We delete all existing
     * BookFile records (S3 + DB) before creating the new source to avoid a unique
     * (book_id, format) conflict when the new source format matches an existing
     * derived format (e.g. switching from docx to fb2).
     */
    public function queueSourceUpload(Book $book, UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $format = BookFileFormat::from($ext);
        $tempPath = $this->moveToTemp($file);

        $book->load('files');
        $book->files->each(function (BookFile $bookFile): void {
            if ($bookFile->path !== null) {
                Storage::disk('s3-private')->delete($bookFile->path);
            }
            $bookFile->delete();
        });

        $bookFile = BookFile::create([
            'book_id' => $book->id,
            'format' => $format,
            'is_source' => true,
            'status' => BookFileStatus::Pending,
            'path' => null,
            'error_message' => null,
        ]);

        UploadSourceFile::dispatch($bookFile->id, $tempPath)->afterCommit();
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

    private function streamToS3(UploadedFile $file, string $s3Path): string
    {
        $tempPath = $file->store('temp', 'local') ?: throw new RuntimeException('Cannot store temp file.');

        try {
            $handle = Storage::disk('local')->readStream($tempPath);

            if (! is_resource($handle)) {
                throw new RuntimeException("Cannot open temp stream for reading: {$tempPath}");
            }

            Storage::disk('s3-private')->writeStream($s3Path, $handle);
        } finally {
            Storage::disk('local')->delete($tempPath);
        }

        return $s3Path;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertBookFile(Book $book, BookFileFormat $format, bool $isSource, array $attributes): BookFile
    {
        $existing = $this->findBookFile($book, $format, isSource: $isSource);

        if ($existing instanceof BookFile) {
            if ($existing->path !== null) {
                Storage::disk('s3-private')->delete($existing->path);
            }

            $existing->update($attributes);

            return $existing;
        }

        return BookFile::create([
            'book_id' => $book->id,
            'format' => $format,
            'is_source' => $isSource,
            ...$attributes,
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

    private function moveToTemp(UploadedFile $file): string
    {
        return $file->store('temp', 'local') ?: throw new RuntimeException('Can`t create temporary file');
    }
}
