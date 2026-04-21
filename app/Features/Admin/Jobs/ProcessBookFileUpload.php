<?php

declare(strict_types=1);

namespace App\Features\Admin\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
    ) {
        $this->onQueue('uploads');
    }

    /**
     * Execute the job.
     *
     * @deprecated Replaced by UploadSourceFile in Phase 13.3. Dispatch is disabled in BookAdminService.
     */
    public function handle(): void
    {
        // epub_path column was dropped in Phase 13.1.
        // This job is replaced by UploadSourceFile in Phase 13.3 and must not be dispatched.
        if (file_exists($this->tempPath)) {
            unlink($this->tempPath);
        }
    }
}
