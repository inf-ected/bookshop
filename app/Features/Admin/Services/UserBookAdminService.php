<?php

declare(strict_types=1);

namespace App\Features\Admin\Services;

use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class UserBookAdminService
{
    /**
     * Revoke a user's access to a book.
     * Rule 81: sets revoked_at = now().
     */
    public function revoke(UserBook $userBook): void
    {
        $userBook->revoked_at = now();
        $userBook->save();
    }

    /**
     * Restore a previously revoked user book access.
     */
    public function restore(UserBook $userBook): void
    {
        $userBook->revoked_at = null;
        $userBook->save();
    }

    /**
     * Manually grant a book to a user.
     * Rule 82: creates user_books with order_id=null and granted_at=now(). Logs reason if provided.
     * If the user has an active (non-revoked) record the grant is rejected.
     * If the user has a revoked record it is restored rather than creating a duplicate.
     */
    public function grant(User $user, Book $book, ?string $reason = null): UserBook
    {
        $existing = UserBook::query()
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->first();

        if ($existing !== null && $existing->revoked_at === null) {
            throw new InvalidArgumentException('Пользователь уже владеет этой книгой.');
        }

        if ($existing !== null) {
            // Restore a previously revoked record instead of creating a duplicate.
            $existing->order_id = null;
            $existing->granted_at = now();
            $existing->revoked_at = null;
            $existing->save();
            $userBook = $existing;
        } else {
            $userBook = UserBook::query()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'order_id' => null,
                'granted_at' => now(),
            ]);
        }

        if ($reason !== null) {
            Log::info('Admin manually granted book to user.', [
                'admin_action' => 'grant_book',
                'user_id' => $user->id,
                'book_id' => $book->id,
                'user_book_id' => $userBook->id,
                'reason' => $reason,
            ]);
        }

        return $userBook;
    }
}
