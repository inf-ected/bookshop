<?php

declare(strict_types=1);

namespace App\Features\Admin\Services;

use App\Enums\BookStatus;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array{total_books: int, published_books: int, draft_books: int, featured_books: int}
     */
    public function getStats(): array
    {
        $row = DB::table('books')
            ->selectRaw('COUNT(*) as total_books')
            ->selectRaw('SUM(status = ?) as published_books', [BookStatus::Published->value])
            ->selectRaw('SUM(status = ?) as draft_books', [BookStatus::Draft->value])
            ->selectRaw('SUM(is_featured = 1) as featured_books')
            ->first();

        return [
            'total_books' => (int) ($row->total_books ?? 0),
            'published_books' => (int) ($row->published_books ?? 0),
            'draft_books' => (int) ($row->draft_books ?? 0),
            'featured_books' => (int) ($row->featured_books ?? 0),
        ];
    }
}
