<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BookStatus;
use App\Enums\UserRole;
use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * Only admins can manage books in the admin panel.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role !== UserRole::Admin) {
            return false;
        }

        return null;
    }

    /**
     * Admins can view any book.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Admins can view any book.
     */
    public function view(User $user, Book $book): bool
    {
        return true;
    }

    /**
     * Admins can create books.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Admins can update books.
     */
    public function update(User $user, Book $book): bool
    {
        return true;
    }

    /**
     * A book can be deleted only if it is a draft AND has no purchases.
     * Rule 16: published book with purchases cannot be deleted.
     * Rule 18: draft books with no purchases can be deleted.
     */
    public function delete(User $user, Book $book): bool
    {
        if ($book->status === BookStatus::Published) {
            return false;
        }

        return ! $book->hasAnyPurchases();
    }
}
