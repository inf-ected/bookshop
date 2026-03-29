<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookStatus;
use App\Jobs\ProcessBookFileUpload;
use App\Models\Book;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BookAdminService
{
    public function __construct(private readonly BookFileService $fileService) {}

    /**
     * Create a new book record, upload cover files synchronously, and dispatch
     * the epub upload job if an epub file is provided.
     *
     * @param  array<string, mixed>  $data  Validated form data (price in rubles)
     */
    public function createBook(
        array $data,
        ?UploadedFile $cover,
        ?UploadedFile $coverThumb,
        ?UploadedFile $epub,
    ): Book {
        return DB::transaction(function () use ($data, $cover, $coverThumb, $epub): Book {
            $book = new Book;
            $book->title = $data['title'];
            $book->slug = $data['slug'];
            $book->status = BookStatus::Draft;
            $book->price = (int) round((float) $data['price'] * 100);
            $book->currency = 'RUB';
            $book->annotation = $data['annotation'] ?? null;
            $book->excerpt = $data['excerpt'] ?? null;
            $book->fragment = $data['fragment'] ?? null;
            $book->is_featured = (bool) ($data['is_featured'] ?? false);
            $book->sort_order = (int) ($data['sort_order'] ?? 0);
            $book->save();

            if ($cover !== null) {
                $book->cover_path = $this->fileService->uploadCover($book, $cover);
            }

            if ($coverThumb !== null) {
                $book->cover_thumb_path = $this->fileService->uploadCoverThumb($book, $coverThumb);
            }

            if ($cover !== null || $coverThumb !== null) {
                $book->save();
            }

            if ($epub !== null) {
                $tempPath = $epub->store('temp', 'local');

                if ($tempPath !== false) {
                    ProcessBookFileUpload::dispatch(
                        $book->id,
                        storage_path('app/'.$tempPath),
                        $epub->getClientOriginalExtension(),
                    );
                }
            }

            return $book;
        });
    }

    /**
     * Update an existing book record, upload cover files synchronously, and
     * dispatch the epub upload job if a new epub file is provided.
     *
     * @param  array<string, mixed>  $data  Validated form data (price in rubles)
     */
    public function updateBook(
        Book $book,
        array $data,
        ?UploadedFile $cover,
        ?UploadedFile $coverThumb,
        ?UploadedFile $epub,
    ): Book {
        return DB::transaction(function () use ($book, $data, $cover, $coverThumb, $epub): Book {
            $book->title = $data['title'];
            $book->slug = $data['slug'];
            $book->status = BookStatus::from($data['status']);
            $book->price = (int) round((float) $data['price'] * 100);
            $book->annotation = $data['annotation'] ?? null;
            $book->excerpt = $data['excerpt'] ?? null;
            $book->fragment = $data['fragment'] ?? null;
            $book->is_featured = (bool) ($data['is_featured'] ?? false);
            $book->sort_order = (int) ($data['sort_order'] ?? 0);

            if ($cover !== null) {
                $book->cover_path = $this->fileService->uploadCover($book, $cover);
            }

            if ($coverThumb !== null) {
                $book->cover_thumb_path = $this->fileService->uploadCoverThumb($book, $coverThumb);
            }

            $book->save();

            if ($epub !== null) {
                $tempPath = $epub->store('temp', 'local');

                if ($tempPath !== false) {
                    ProcessBookFileUpload::dispatch(
                        $book->id,
                        storage_path('app/'.$tempPath),
                        $epub->getClientOriginalExtension(),
                    );
                }
            }

            return $book;
        });
    }
}
