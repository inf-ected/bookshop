<?php

declare(strict_types=1);

namespace App\Features\Admin\Jobs;

use App\Enums\BookFileStatus;
use App\Features\Admin\Services\BookConversionService;
use App\Models\BookFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadSourceFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  int  $bookFileId  ID of the BookFile record (is_source=true, status=pending)
     * @param  string  $tempPath  Absolute path to the temp file on local disk
     */
    public function __construct(
        public readonly int $bookFileId,
        public readonly string $tempPath,
    ) {
        $this->onQueue('default');
    }

    public function handle(BookConversionService $conversionService): void
    {
        $bookFile = BookFile::find($this->bookFileId);

        if (! $bookFile instanceof BookFile) {
            @unlink($this->tempPath);

            return;
        }

        $ext = $bookFile->format->extension();
        $s3Path = "books/{$bookFile->book_id}/".Str::uuid().'.'.$ext;

        $handle = fopen($this->tempPath, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open temp file for reading: {$this->tempPath}");
        }

        Storage::disk('s3-private')->put($s3Path, $handle);
        fclose($handle);

        $bookFile->update([
            'path' => $s3Path,
            'status' => BookFileStatus::Ready,
        ]);

        @unlink($this->tempPath);

        $conversionService->dispatchConversions($bookFile);
    }

    /**
     * Called by Laravel after all retry attempts are exhausted.
     * Marks the BookFile as failed and cleans up the temp file.
     */
    public function failed(\Throwable $e): void
    {
        @unlink($this->tempPath);

        $bookFile = BookFile::find($this->bookFileId);
        $bookFile?->update([
            'status' => BookFileStatus::Failed,
            'error_message' => mb_substr($e->getMessage(), 0, 2000),
        ]);
    }
}
