<?php

declare(strict_types=1);

namespace App\Features\Admin\Services;

use App\Enums\BookStatus;
use App\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

readonly class BookAdminService
{
    public function __construct(
        private BookCoverService $coverService,
        private BookFileService $fileService,
    ) {}

    /**
     * Create a new book record, upload cover files synchronously, and dispatch
     * the source file upload job if a source file is provided.
     *
     * @param  array<string, mixed>  $data  Validated form data (price in shop currency)
     *
     * @throws Throwable
     */
    public function createBook(
        array $data,
        ?UploadedFile $cover,
        ?UploadedFile $coverThumb,
        ?UploadedFile $sourceFile,
    ): Book {
        return DB::transaction(function () use ($data, $cover, $coverThumb, $sourceFile): Book {
            $book = new Book;
            $book->title = $data['title'];
            $book->slug = $data['slug'];
            $book->status = BookStatus::Draft;
            $book->price = (int) round((float) $data['price'] * 100);
            $book->currency = config('shop.currency_code');
            $book->annotation = $data['annotation'] ?? null;
            $book->excerpt = $data['excerpt'] ?? null;
            $book->fragment = $data['fragment'] ?? null;
            $book->is_featured = (bool) ($data['is_featured'] ?? false);
            $book->is_adult = (bool) ($data['is_adult'] ?? false);
            $book->sort_order = (int) ($data['sort_order'] ?? 0);
            $book->save();

            if ($cover !== null) {
                $book->cover_path = $this->coverService->uploadCover($book, $cover);
            }

            if ($coverThumb !== null) {
                $book->cover_thumb_path = $this->coverService->uploadCoverThumb($book, $coverThumb);
            }

            if ($cover !== null || $coverThumb !== null) {
                $book->save();
            }

            if ($sourceFile !== null) {
                $this->fileService->queueSourceUpload($book, $sourceFile);
            }

            return $book;
        });
    }

    /**
     * Update an existing book record, upload cover files synchronously, and
     * dispatch the source file upload job if a new source file is provided.
     *
     * @param  array<string, mixed>  $data  Validated form data (price in shop currency)
     *
     * @throws InvalidArgumentException
     * @throws Throwable if Rule 17 is violated (cannot unpublish a book with purchases)
     */
    public function updateBook(
        Book $book,
        array $data,
        ?UploadedFile $cover,
        ?UploadedFile $coverThumb,
        ?UploadedFile $sourceFile,
    ): Book {
        $newStatus = BookStatus::from($data['status']);

        // Rule 17: a published book that has purchases cannot be unpublished.
        if ($book->status === BookStatus::Published && $newStatus === BookStatus::Draft && $book->hasAnyPurchases()) {
            throw new InvalidArgumentException('Нельзя снять с публикации книгу, у которой есть покупки.');
        }

        // Cannot publish a book that has no client-accessible ready file.
        if ($newStatus === BookStatus::Published && ! $book->hasClientReadyFile()) {
            throw new InvalidArgumentException('Нельзя опубликовать книгу без готового файла для скачивания.');
        }

        return DB::transaction(function () use ($book, $data, $cover, $coverThumb, $sourceFile): Book {
            $book->title = $data['title'];
            $book->slug = $data['slug'];
            $book->status = BookStatus::from($data['status']);
            $book->price = (int) round((float) $data['price'] * 100);
            $book->annotation = $data['annotation'] ?? null;
            $book->excerpt = $data['excerpt'] ?? null;
            $book->fragment = $data['fragment'] ?? null;
            $book->is_featured = (bool) ($data['is_featured'] ?? false);
            $book->is_adult = (bool) ($data['is_adult'] ?? false);
            $book->sort_order = (int) ($data['sort_order'] ?? 0);

            if ($cover !== null) {
                $book->cover_path = $this->coverService->uploadCover($book, $cover);
            }

            if ($coverThumb !== null) {
                $book->cover_thumb_path = $this->coverService->uploadCoverThumb($book, $coverThumb);
            }

            $book->save();

            if ($sourceFile !== null) {
                $this->fileService->queueSourceUpload($book, $sourceFile);
            }

            return $book;
        });
    }

    /**
     * Toggle the book's published/draft status.
     *
     * Rule 17: cannot unpublish a book that has purchases.
     * Cannot publish a book without an epub file.
     *
     * @throws InvalidArgumentException on rule violation
     */
    public function toggleStatus(Book $book): Book
    {
        if ($book->status === BookStatus::Published) {
            if ($book->hasAnyPurchases()) {
                throw new InvalidArgumentException('Нельзя снять с публикации книгу, у которой есть покупки.');
            }
            $book->status = BookStatus::Draft;
        } else {
            if (! $book->hasClientReadyFile()) {
                throw new InvalidArgumentException('Нельзя опубликовать книгу без готового файла для скачивания.');
            }
            $book->status = BookStatus::Published;
        }

        $book->save();

        return $book;
    }

    /**
     * Toggle the book's availability for sale.
     * Can always be toggled regardless of purchase history (unlike draft/published).
     * Only meaningful for published books — draft books are never available.
     */
    public function toggleAvailability(Book $book): Book
    {
        $book->is_available = ! $book->is_available;
        $book->save();

        return $book;
    }

    /**
     * Toggle the book's featured flag.
     */
    public function toggleFeatured(Book $book): Book
    {
        $book->is_featured = ! $book->is_featured;
        $book->save();

        return $book;
    }

    /**
     * Delete a book and its associated files.
     *
     * Caller must verify BookPolicy::delete() before calling this method.
     */
    public function deleteBook(Book $book): void
    {
        $book->load('files');

        $this->coverService->deleteCover($book);
        $this->fileService->deleteAll($book);

        $book->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Book>
     */
    public function listBooks(int $perPage = 15): LengthAwarePaginator
    {
        return Book::query()->ordered()->paginate($perPage);
    }
}
