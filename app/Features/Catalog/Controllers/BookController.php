<?php

declare(strict_types=1);

namespace App\Features\Catalog\Controllers;

use App\Enums\BookStatus;
use App\Features\Catalog\Services\CatalogService;
use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct(private readonly CatalogService $catalogService) {}

    public function index(Request $request): View
    {
        $books = $this->catalogService->listPublished();
        $user = $request->user();
        $ownedBookIds = $user
            ? $this->catalogService->getOwnedBookIds($user)
            : collect();

        return view('books.index', compact('books', 'ownedBookIds'));
    }

    public function show(Request $request, Book $book): View
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        $user = $request->user();
        $isOwned = $user
            ? $this->catalogService->isOwnedByUser($book, $user)
            : false;

        return view('books.show', compact('book', 'isOwned'));
    }

    public function fragment(Book $book): View
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        return view('books.fragment', compact('book'));
    }
}
