<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BookStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBookRequest;
use App\Http\Requests\Admin\UpdateBookRequest;
use App\Models\Book;
use App\Services\BookFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct(private readonly BookFileService $fileService) {}

    public function index(): View
    {
        $books = Book::query()->ordered()->paginate(15);

        return view('admin.books.index', compact('books'));
    }

    public function create(): View
    {
        return view('admin.books.create');
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $data = $request->validated();

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

        if ($request->hasFile('cover')) {
            $book->cover_path = $this->fileService->uploadCover($book, $request->file('cover'));
        }

        if ($request->hasFile('cover_thumb')) {
            $book->cover_thumb_path = $this->fileService->uploadCoverThumb($book, $request->file('cover_thumb'));
        }

        if ($request->hasFile('epub')) {
            $book->epub_path = $this->fileService->uploadEpub($book, $request->file('epub'));
        }

        $book->save();

        return redirect()->route('admin.books.edit', $book)
            ->with('success', 'Книга создана.');
    }

    public function edit(Book $book): View
    {
        return view('admin.books.edit', compact('book'));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $data = $request->validated();

        $newStatus = BookStatus::from($data['status']);

        // Rule 17: cannot unpublish a book that has purchases
        if ($book->status === BookStatus::Published && $newStatus === BookStatus::Draft) {
            if ($this->bookHasPurchases($book)) {
                return redirect()->route('admin.books.edit', $book)
                    ->withErrors(['status' => 'Нельзя снять с публикации книгу, у которой есть покупки.']);
            }
        }

        $book->title = $data['title'];
        $book->slug = $data['slug'];
        $book->status = $newStatus;
        $book->price = (int) round((float) $data['price'] * 100);
        $book->annotation = $data['annotation'] ?? null;
        $book->excerpt = $data['excerpt'] ?? null;
        $book->fragment = $data['fragment'] ?? null;
        $book->is_featured = (bool) ($data['is_featured'] ?? false);
        $book->sort_order = (int) ($data['sort_order'] ?? 0);

        if ($request->hasFile('cover')) {
            $book->cover_path = $this->fileService->uploadCover($book, $request->file('cover'));
        }

        if ($request->hasFile('cover_thumb')) {
            $book->cover_thumb_path = $this->fileService->uploadCoverThumb($book, $request->file('cover_thumb'));
        }

        if ($request->hasFile('epub')) {
            $book->epub_path = $this->fileService->uploadEpub($book, $request->file('epub'));
        }

        $book->save();

        return redirect()->route('admin.books.edit', $book->fresh())
            ->with('success', 'Книга обновлена.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        Gate::authorize('delete', $book);

        $this->fileService->deleteCover($book);
        $this->fileService->deleteEpub($book);

        $book->delete();

        return redirect()->route('admin.books.index')
            ->with('success', 'Книга удалена.');
    }

    public function toggleStatus(Book $book): JsonResponse
    {
        if ($book->status === BookStatus::Published) {
            // Rule 17: cannot unpublish if purchases exist
            if ($this->bookHasPurchases($book)) {
                return response()->json(
                    ['error' => 'Нельзя снять с публикации книгу, у которой есть покупки.'],
                    422
                );
            }
            $book->status = BookStatus::Draft;
        } else {
            $book->status = BookStatus::Published;
        }

        $book->save();

        return response()->json(['status' => $book->status->value]);
    }

    public function toggleFeatured(Book $book): JsonResponse
    {
        $book->is_featured = ! $book->is_featured;
        $book->save();

        return response()->json(['is_featured' => $book->is_featured]);
    }

    /**
     * Check whether a book has any purchase records (user_books).
     * Checks table existence to avoid hard dependency before Phase 5.
     */
    private function bookHasPurchases(Book $book): bool
    {
        if (! Schema::hasTable('user_books')) {
            return false;
        }

        return DB::table('user_books')
            ->where('book_id', $book->id)
            ->exists();
    }
}
