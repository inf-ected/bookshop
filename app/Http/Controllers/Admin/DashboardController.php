<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BookStatus;
use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_books' => Book::query()->count(),
            'published_books' => Book::query()->published()->count(),
            'draft_books' => Book::query()->where('status', BookStatus::Draft)->count(),
            'featured_books' => Book::query()->where('is_featured', true)->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
