<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
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

        return view('home', compact('books'));
    }
}
