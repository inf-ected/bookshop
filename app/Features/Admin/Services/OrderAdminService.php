<?php

declare(strict_types=1);

namespace App\Features\Admin\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\UserBook;
use Illuminate\Support\Facades\DB;

class OrderAdminService
{
    /**
     * Refund an order.
     * Rule 80: single DB transaction — sets status=Refunded and revokes all linked UserBook records.
     *
     * @throws \InvalidArgumentException if the order is not in Paid status
     * @throws \Throwable
     */
    public function refund(Order $order): void
    {
        if ($order->status !== OrderStatus::Paid) {
            throw new \InvalidArgumentException('Только оплаченный заказ может быть возвращён.');
        }

        DB::transaction(function () use ($order): void {
            $order->status = OrderStatus::Refunded;
            $order->save();

            UserBook::query()
                ->where('order_id', $order->id)
                ->update(['revoked_at' => now()]);
        });
    }
}
