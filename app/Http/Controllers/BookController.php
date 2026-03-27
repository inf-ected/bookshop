<?php

namespace App\Http\Controllers;

use App\Enums\BookStatus;
use App\Models\Book;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(): View
    {
        $books = Book::query()
            ->published()
            ->ordered()
            ->get();

        return view('books.index', compact('books'));
    }

    public function show(Book $book): View
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        return view('books.show', compact('book'));
    }

    public function fragment(Book $book): View
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        return view('books.fragment', compact('book'));
    }
}
