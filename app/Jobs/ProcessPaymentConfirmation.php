<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Events\OrderPaid;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\UserBook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaymentConfirmation implements ShouldQueue
{
    use Queueable;

    /** Maximum number of attempts before the job is marked as failed. */
    public int $tries = 3;

    /** Seconds to wait before retrying after a failure. */
    public int $backoff = 10;

    public function __construct(
        public readonly int $orderId,
        public readonly string $stripePaymentIntentId,
        public readonly string $stripeSessionId,
    ) {}

    public function handle(): void
    {
        Log::info('ProcessPaymentConfirmation: starting', [
            'order_id' => $this->orderId,
            'session_id' => $this->stripeSessionId,
        ]);

        /** @var Order|null $paidOrder */
        $paidOrder = DB::transaction(function (): ?Order {
            // Rule 30: idempotency — lock the row so concurrent workers cannot
            // both pass the guard and process the same order twice.
            $order = Order::query()->lockForUpdate()->find($this->orderId);

            if ($order === null) {
                Log::warning('ProcessPaymentConfirmation: order not found', ['order_id' => $this->orderId]);

                return null;
            }

            if ($order->status === OrderStatus::Paid) {
                Log::info('ProcessPaymentConfirmation: already paid, skipping', ['order_id' => $this->orderId]);

                return null;
            }

            $order->status = OrderStatus::Paid;
            $order->paid_at = now();
            $order->stripe_payment_intent_id = $this->stripePaymentIntentId;
            $order->save();

            // Rule 31: create user_books records for each order item
            $order->load('items');

            foreach ($order->items as $item) {
                UserBook::query()->firstOrCreate(
                    ['user_id' => $order->user_id, 'book_id' => $item->book_id],
                    ['order_id' => $order->id, 'granted_at' => now()],
                );
            }

            // Rule 31: clear the user's cart by user_id (no session in queue context)
            CartItem::query()
                ->where('user_id', $order->user_id)
                ->delete();

            Log::info('ProcessPaymentConfirmation: order marked paid', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'books_granted' => $order->items->count(),
            ]);

            return $order;
        });

        // Rule 31/32: dispatch OrderPaid event AFTER the transaction commits,
        // so the listener sees the persisted paid state. Skip if already paid (idempotency).
        if ($paidOrder !== null) {
            Log::info('ProcessPaymentConfirmation: dispatching OrderPaid event', ['order_id' => $paidOrder->id]);
            OrderPaid::dispatch($paidOrder->fresh());
        }
    }
}
