<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BookStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBookRequest;
use App\Http\Requests\Admin\UpdateBookRequest;
use App\Models\Book;
use App\Services\BookAdminService;
use App\Services\BookFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct(
        private readonly BookAdminService $bookAdminService,
        private readonly BookFileService $fileService,
    ) {}

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
        $book = $this->bookAdminService->createBook(
            $request->validated(),
            $request->file('cover'),
            $request->file('cover_thumb'),
            $request->file('epub'),
        );

        return redirect()->route('admin.books.edit', $book)
            ->with('success', 'Книга создана.');
    }

    public function edit(Book $book): View
    {
        return view('admin.books.edit', compact('book'));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        try {
            $book = $this->bookAdminService->updateBook(
                $book,
                $request->validated(),
                $request->file('cover'),
                $request->file('cover_thumb'),
                $request->file('epub'),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.books.edit', $book)
                ->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.books.edit', $book)
            ->with('success', 'Книга обновлена.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        if (! Gate::allows('delete', $book)) {
            return redirect()->route('admin.books.index')
                ->with('error', 'Нельзя удалить опубликованную книгу.');
        }

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
            if ($book->hasAnyPurchases()) {
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
}
