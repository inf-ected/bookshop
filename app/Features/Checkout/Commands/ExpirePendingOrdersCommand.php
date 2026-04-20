<?php

declare(strict_types=1);

namespace App\Features\Checkout\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingOrdersCommand extends Command
{
    protected $signature = 'app:expire-pending-orders';

    protected $description = 'Set status=failed on pending order_transactions past their expires_at, and mark the associated orders as failed';

    public function handle(): int
    {
        $expiredCount = 0;

        // Primary: find pending transactions whose expires_at has passed.
        // This is more accurate than using orders.created_at because Stripe
        // returns the actual session expiry time (default 24 h, configurable).
        $expiredTransactions = OrderTransaction::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expiredTransactions as $transaction) {
            DB::transaction(function () use ($transaction, &$expiredCount): void {
                /** @var OrderTransaction $transaction */
                $transaction->status = 'expired';
                $transaction->save();

                $order = Order::query()
                    ->where('id', $transaction->order_id)
                    ->where('status', OrderStatus::Pending)
                    ->first();

                if ($order !== null) {
                    $order->status = OrderStatus::Failed;
                    $order->save();
                    $expiredCount++;
                }
            });
        }

        $this->info("Expired $expiredCount pending order(s).");

        return self::SUCCESS;
    }
}
