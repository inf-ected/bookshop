<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Book;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessBookFileUpload implements ShouldQueue
{
    use Queueable;

    /** Maximum number of attempts before the job is marked as failed. */
    public int $tries = 3;

    /** Seconds to wait before retrying after a failure. */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     *
     * @param  string  $tempPath  Absolute path to the temp file on disk
     * @param  string  $originalExtension  Original file extension (e.g. 'epub')
     */
    public function __construct(
        public readonly int $bookId,
        public readonly string $tempPath,
        public readonly string $originalExtension,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $book = Book::query()->find($this->bookId);

        if (! $book instanceof Book) {
            return;
        }

        if ($book->epub_path) {
            Storage::disk('s3-private')->delete($book->epub_path);
        }

        $epubPath = 'epubs/'.Str::uuid().'.epub';

        $content = file_get_contents($this->tempPath);

        if ($content === false) {
            throw new \RuntimeException("Failed to read temp file: {$this->tempPath}");
        }

        $stored = Storage::disk('s3-private')->put($epubPath, $content, 'private');

        if ($stored === false) {
            throw new \RuntimeException("Failed to upload epub to S3: {$epubPath}");
        }

        $book->update(['epub_path' => $epubPath]);

        if (file_exists($this->tempPath)) {
            unlink($this->tempPath);
        }
    }
}
