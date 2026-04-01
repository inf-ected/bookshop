<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookStatus;
use App\Models\Book;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(): View
    {
        $books = Book::query()
            ->published()
            ->ordered()
            ->get();

        $ownedBookIds = Auth::user()?->userBooks()->pluck('book_id') ?? collect();

        return view('books.index', compact('books', 'ownedBookIds'));
    }

    public function show(Book $book): View
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        $isOwned = Auth::user()?->userBooks()->where('book_id', $book->id)->exists() ?? false;

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
