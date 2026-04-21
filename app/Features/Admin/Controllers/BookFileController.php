<?php

declare(strict_types=1);

namespace App\Features\Admin\Controllers;

use App\Enums\BookFileFormat;
use App\Enums\BookFileStatus;
use App\Features\Admin\Jobs\ConvertBookFormat;
use App\Features\Admin\Jobs\UploadSourceFile;
use App\Features\Admin\Requests\UploadBookFileRequest;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookFileController extends Controller
{
    /**
     * Upload a source file or re-upload a specific derived format.
     *
     * If no format is specified (or the format is docx), treats as source upload:
     *  - Deletes all existing derived BookFile S3 paths
     *  - Creates/updates source BookFile record (is_source=true, status=pending)
     *  - Stores file to local temp dir and dispatches UploadSourceFile
     *
     * If format is epub or fb2 (derived), stores directly to S3:
     *  - Creates/updates the BookFile record (is_source=false, status=ready)
     *  - No conversion triggered
     */
    public function store(UploadBookFileRequest $request, Book $book): RedirectResponse
    {
        $uploadedFile = $request->file('file');
        $formatValue = $request->validated('format');

        if ($formatValue !== null) {
            $format = BookFileFormat::from($formatValue);

            // Derived format re-upload — store directly to S3, mark ready.
            $ext = $format->extension();
            $s3Path = "books/{$book->id}/".Str::uuid().'.'.$ext;

            $handle = fopen($uploadedFile->getRealPath(), 'r');

            if ($handle === false) {
                throw new \RuntimeException('Cannot open uploaded file for reading.');
            }

            Storage::disk('s3-private')->put($s3Path, $handle);
            fclose($handle);

            $existing = BookFile::query()
                ->where('book_id', $book->id)
                ->where('format', $format)
                ->where('is_source', false)
                ->first();

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

            return redirect()->back()->with('success', 'Файл загружен.');
        }

        // Source upload flow — dispatchConversions() will reset derived files (Rule 8).
        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        $sourceFormat = BookFileFormat::from($ext);

        // Create or reset the source BookFile record.
        $sourceFile = BookFile::query()
            ->where('book_id', $book->id)
            ->where('format', $sourceFormat)
            ->where('is_source', true)
            ->first();

        $basePath = tempnam(sys_get_temp_dir(), 'bookshop_source_');
        $tempPath = $basePath.'.'.$ext;
        $uploadedFile->move(dirname($tempPath), basename($tempPath));
        @unlink($basePath);

        if ($sourceFile instanceof BookFile) {
            if ($sourceFile->path !== null) {
                Storage::disk('s3-private')->delete($sourceFile->path);
            }

            $sourceFile->update([
                'status' => BookFileStatus::Pending,
                'path' => null,
                'error_message' => null,
            ]);
        } else {
            $sourceFile = BookFile::create([
                'book_id' => $book->id,
                'format' => $sourceFormat,
                'status' => BookFileStatus::Pending,
                'is_source' => true,
            ]);
        }

        UploadSourceFile::dispatch($sourceFile->id, $tempPath);

        return redirect()->back()->with('success', 'Исходный файл принят в обработку.');
    }

    /**
     * Generate a temporary S3 URL for admin download (any format including DOCX).
     *
     * Admin can download ANY format — no DOCX gate applies here (Rule 19).
     */
    public function download(Book $book, BookFile $bookFile): RedirectResponse
    {
        abort_unless($bookFile->book_id === $book->id, 404);
        abort_unless($bookFile->path !== null, 404);

        $url = Storage::disk('s3-private')->temporaryUrl(
            $bookFile->path,
            now()->addMinutes(5),
        );

        return redirect($url);
    }

    /**
     * Reset a failed BookFile to pending and re-dispatch ConvertBookFormat.
     */
    public function retry(Book $book, BookFile $bookFile): RedirectResponse
    {
        abort_unless($bookFile->book_id === $book->id, 404);

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

        return redirect()->back()->with('success', 'Конвертация запущена повторно.');
    }

    /**
     * Return JSON array of all BookFile records for the book.
     * Used by the admin UI for polling file conversion statuses.
     */
    public function status(Book $book): JsonResponse
    {
        $files = $book->files()
            ->get()
            ->map(fn (BookFile $bf) => [
                'id' => $bf->id,
                'format' => $bf->format->value,
                'status' => $bf->status->value,
                'error_message' => $bf->error_message,
                'updated_at' => $bf->updated_at,
            ]);

        return response()->json($files);
    }
}
