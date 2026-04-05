<?php

declare(strict_types=1);

namespace App\Features\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Book;
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

        $ownedBookIds = Auth::user()?->userBooks()->pluck('book_id') ?? collect();

        return view('home', compact('books', 'ownedBookIds'));
    }
}
