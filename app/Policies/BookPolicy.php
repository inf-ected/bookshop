<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookPolicy
{
    protected function isUserOwner(User $user, Book $book): bool
    {
        return $user
            ->userBooks()
            ->where('book_id', $book->id)
            ->exists();
    }

    /**
     * Admins can view any book.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admins can view any book.
     */
    public function view(User $user, Book $book): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admins can create books.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admins can update books.
     */
    public function update(User $user, Book $book): bool
    {
        return $user->isAdmin();
    }

    /**
     * A book can be deleted only if it is a draft AND has no purchases.
     * Rule 16: published books cannot be deleted.
     * Rule 18: draft books with no purchases can be deleted.
     */
    public function delete(User $user, Book $book): Response|bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($book->isPublished()) {
            return Response::deny('Нельзя удалить опубликованную книгу.');
        }

        if ($book->hasAnyPurchases()) {
            return Response::deny('Нельзя удалить книгу, у которой есть покупки.');
        }

        return true;
    }

    public function download(User $user, Book $book): bool
    {
        return $this->isUserOwner($user, $book);
    }
}
