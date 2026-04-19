<?php

declare(strict_types=1);

namespace App\Features\Checkout\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Features\Checkout\Events\OrderPaid;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderTransaction;
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
        public readonly string $transactionId,
        public readonly string $sessionId,
        public readonly PaymentGateway $provider = PaymentGateway::Stripe,
    ) {
        $this->onQueue('payments');
    }

    public function handle(): void
    {
        Log::info('ProcessPaymentConfirmation: starting', [
            'order_id' => $this->orderId,
            'session_id' => $this->sessionId,
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
            $order->save();

            // Update the OrderTransaction to succeeded and store the provider transaction ID
            // so the provider-specific data stays in order_transactions, not on orders.
            $transaction = OrderTransaction::query()
                ->where('order_id', $this->orderId)
                ->where('provider', $this->provider->value)
                ->whereRaw("json_extract(provider_data, '$.session_id') = ?", [$this->sessionId])
                ->first();

            if ($transaction !== null) {
                $providerData = $transaction->provider_data;
                $providerData['transaction_id'] = $this->transactionId;

                $transaction->provider_data = $providerData;
                $transaction->status = 'succeeded';
                $transaction->expires_at = null;
                $transaction->save();
            }

            // Rule 31: create user_books records for each order item.
            // If a revoked record exists for this user+book, restore it rather than
            // leaving revoked_at set (firstOrCreate would find the revoked row and skip it).
            $order->load('items');

            foreach ($order->items as $item) {
                $userBook = UserBook::query()
                    ->where('user_id', $order->user_id)
                    ->where('book_id', $item->book_id)
                    ->first();

                if ($userBook !== null) {
                    // Restore a previously revoked (or already active) record.
                    $userBook->order_id = $order->id;
                    $userBook->granted_at = now();
                    $userBook->revoked_at = null;
                    $userBook->save();
                } else {
                    UserBook::query()->create([
                        'user_id' => $order->user_id,
                        'book_id' => $item->book_id,
                        'order_id' => $order->id,
                        'granted_at' => now(),
                    ]);
                }
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
