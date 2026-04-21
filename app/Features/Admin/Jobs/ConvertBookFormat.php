<?php

declare(strict_types=1);

namespace App\Features\Admin\Jobs;

use App\Enums\BookFileStatus;
use App\Features\Admin\Services\BookConversionService;
use App\Models\BookFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ConvertBookFormat implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(
        public readonly int $bookFileId,
        public readonly int $sourceBookFileId,
    ) {
        $this->onQueue('default');
    }

    public function handle(BookConversionService $service): void
    {
        $target = BookFile::find($this->bookFileId);

        if (! $target instanceof BookFile || $target->status !== BookFileStatus::Pending) {
            return;
        }

        $target->update(['status' => BookFileStatus::Processing]);

        $source = BookFile::find($this->sourceBookFileId);

        if (! $source instanceof BookFile || $source->path === null || ! $source->isReady()) {
            $target->update([
                'status' => BookFileStatus::Failed,
                'error_message' => 'Source file is not available or not ready.',
            ]);

            return;
        }

        // Race condition guard: record the source timestamp before downloading.
        $sourceUpdatedAt = $source->updated_at;

        try {
            $service->executeConversion($source, $target);
        } catch (\Throwable $e) {
            // Re-check source timestamp; if it changed, another upload superseded this one.
            $source->refresh();

            if ($source->updated_at->isAfter($sourceUpdatedAt)) {
                // Source was re-uploaded during conversion — reset to pending so the new job can pick it up.
                $target->update(['status' => BookFileStatus::Pending]);

                return;
            }

            $target->update([
                'status' => BookFileStatus::Failed,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);
        }
    }
}
