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
        return $user->userBooks()->pluck('book_id');
    }

    public function isOwnedByUser(Book $book, User $user): bool
    {
        return $user->userBooks()->where('book_id', $book->id)->exists();
    }
}
