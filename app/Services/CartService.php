<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Book;
use App\Models\CartItem;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class CartService
{
    /**
     * Add a book to the cart for a user or guest.
     *
     * Rule 23: if the user already owns the book via user_books, throw an exception.
     * Rule 24: duplicate cart items are silently ignored (unique constraint).
     *
     * @throws \RuntimeException if the authenticated user already owns the book
     */
    public function addItem(Book $book, ?User $user, string $sessionId): void
    {
        if ($user !== null) {
            $alreadyOwned = UserBook::query()
                ->where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->exists();

            if ($alreadyOwned) {
                throw new \RuntimeException('Эта книга уже есть в вашей библиотеке.');
            }

            try {
                CartItem::query()->create([
                    'user_id' => $user->id,
                    'session_id' => null,
                    'book_id' => $book->id,
                ]);
            } catch (QueryException) {
                // Rule 24: unique constraint violation — book already in cart, silently ignore.
            }

            return;
        }

        try {
            CartItem::query()->create([
                'user_id' => null,
                'session_id' => $sessionId,
                'book_id' => $book->id,
            ]);
        } catch (QueryException) {
            // Rule 24: unique constraint violation — book already in guest cart, silently ignore.
        }
    }

    /**
     * Remove a book from the cart for a user or guest.
     */
    public function removeItem(Book $book, ?User $user, string $sessionId): void
    {
        if ($user !== null) {
            CartItem::query()
                ->where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->delete();

            return;
        }

        CartItem::query()
            ->where('session_id', $sessionId)
            ->where('book_id', $book->id)
            ->delete();
    }

    /**
     * Merge a guest cart into a user's cart on login.
     *
     * Rule 25: duplicates (book already in user cart) are discarded.
     * Guest cart items are deleted after merging.
     */
    public function mergeGuestCart(User $user, string $sessionId): void
    {
        $guestItems = CartItem::query()
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->get();

        foreach ($guestItems as $guestItem) {
            $alreadyOwned = UserBook::query()
                ->where('user_id', $user->id)
                ->where('book_id', $guestItem->book_id)
                ->exists();

            if (! $alreadyOwned) {
                try {
                    CartItem::query()->create([
                        'user_id' => $user->id,
                        'session_id' => null,
                        'book_id' => $guestItem->book_id,
                    ]);
                } catch (QueryException) {
                    // Rule 25: book already in user cart — discard the duplicate.
                }
            }

            $guestItem->delete();
        }
    }

    /**
     * Get all cart items with eager-loaded books for a user or guest.
     *
     * @return Collection<int, CartItem>
     */
    public function getItems(?User $user, string $sessionId): Collection
    {
        if ($user !== null) {
            return CartItem::query()
                ->with('book')
                ->where('user_id', $user->id)
                ->get();
        }

        return CartItem::query()
            ->with('book')
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->get();
    }

    /**
     * Get the total price (in kopecks) of all books in the cart.
     */
    public function getTotal(?User $user, string $sessionId): int
    {
        $items = $this->getItems($user, $sessionId);

        return $items->sum(fn (CartItem $item): int => $item->book->price ?? 0);
    }

    /**
     * Clear all cart items for a user or guest.
     */
    public function clearCart(?User $user, string $sessionId): void
    {
        if ($user !== null) {
            CartItem::query()
                ->where('user_id', $user->id)
                ->delete();

            return;
        }

        CartItem::query()
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->delete();
    }
}
