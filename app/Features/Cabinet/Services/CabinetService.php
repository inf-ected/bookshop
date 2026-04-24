<?php

declare(strict_types=1);

namespace App\Features\Cabinet\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CabinetService
{
    /**
     * @return Collection<int, UserBook>
     */
    public function getUserBooks(User $user): Collection
    {
        return $user->userBooks()
            ->with('book.files')
            ->whereNull('revoked_at')
            ->latest()
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function getUserOrders(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return $user->orders()
            ->with('items.book')
            ->latest()
            ->paginate($perPage);
    }
}
