<?php

declare(strict_types=1);

namespace App\Features\Admin\Controllers;

use App\Enums\BookFileFormat;
use App\Features\Admin\Requests\UploadBookFileRequest;
use App\Features\Admin\Services\BookFileService;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class BookFileController extends Controller
{
    public function __construct(private readonly BookFileService $fileService) {}

    public function store(UploadBookFileRequest $request, Book $book): RedirectResponse
    {
        $formatValue = $request->validated('format');

        if ($formatValue !== null) {
            $this->fileService->uploadDerived($book, $request->file('file'), BookFileFormat::from($formatValue));

            return redirect()->back()->with('success', 'Файл загружен.');
        }

        $this->fileService->queueSourceUpload($book, $request->file('file'));

        return redirect()->back()->with('success', 'Исходный файл принят в обработку.');
    }

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

    public function retry(Book $book, BookFile $bookFile): RedirectResponse
    {
        abort_unless($bookFile->book_id === $book->id, 404);

        $this->fileService->retryConversion($book, $bookFile);

        return redirect()->back()->with('success', 'Конвертация запущена повторно.');
    }

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
