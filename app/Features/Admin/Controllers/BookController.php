<?php

declare(strict_types=1);

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Requests\StoreBookRequest;
use App\Features\Admin\Requests\UpdateBookRequest;
use App\Features\Admin\Services\BookAdminService;
use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct(private readonly BookAdminService $bookAdminService) {}

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
        $response = Gate::inspect('delete', $book);

        if ($response->denied()) {
            return redirect()->route('admin.books.index')
                ->with('error', $response->message());
        }

        $this->bookAdminService->deleteBook($book);

        return redirect()->route('admin.books.index')
            ->with('success', 'Книга удалена.');
    }

    public function toggleStatus(Book $book): JsonResponse
    {
        try {
            $this->bookAdminService->toggleStatus($book);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['status' => $book->status->value]);
    }

    public function toggleFeatured(Book $book): JsonResponse
    {
        $this->bookAdminService->toggleFeatured($book);

        return response()->json(['is_featured' => $book->is_featured]);
    }
}
