<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\UserBook;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $books = Book::query()
            ->published()
            ->featured()
            ->ordered()
            ->get();

        $ownedBookIds = Auth::check()
            ? UserBook::query()->where('user_id', Auth::id())->pluck('book_id')
            : collect();

        return view('home', compact('books', 'ownedBookIds'));
    }
}
