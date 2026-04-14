<?php

declare(strict_types=1);

namespace App\Features\Catalog\Services;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class CatalogService
{
    /**
     * @return Collection<int, Book>
     */
    public function listPublished(): Collection
    {
        return Book::query()
            ->published()
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, Book>
     */
    public function listFeatured(): Collection
    {
        return Book::query()
            ->published()
            ->featured()
            ->ordered()
            ->get();
    }

    /**
     * @return SupportCollection<int, int>
     */
    public function getOwnedBookIds(User $user): SupportCollection
    {
        return $user->userBooks()->whereNull('revoked_at')->pluck('book_id');
    }

    /**
     * Check if a single book is owned by the user.
     *
     * For iterating a list of books, use getOwnedBookIds() instead to avoid N+1 queries.
     */
    public function isOwnedByUser(Book $book, User $user): bool
    {
        return $user->userBooks()->where('book_id', $book->id)->whereNull('revoked_at')->exists();
    }
}
