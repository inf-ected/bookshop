<?php

declare(strict_types=1);

namespace App\Features\Checkout\Services;

use App\Enums\OrderStatus;
use App\Features\Cart\Exceptions\EmptyCartException;
use App\Features\Cart\Services\CartService;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

readonly class OrderService
{
    public function __construct(private CartService $cartService) {}

    /**
     * Create an Order from the user's current cart.
     *
     * Rule 27: Order is created BEFORE Stripe redirect.
     * Rule 28: order_items.price is a snapshot of the book price at purchase time.
     *
     * Note: cart is NOT cleared here — it is cleared by ProcessPaymentConfirmation
     * after the webhook confirms payment. Clearing here would wipe the cart even
     * if Stripe session creation subsequently fails.
     *
     * @throws EmptyCartException if the cart is empty
     * @throws Throwable
     */
    public function createFromCart(User $user, string $sessionId): Order
    {
        $items = $this->cartService->getItems($user, $sessionId);

        if ($items->isEmpty()) {
            throw new EmptyCartException('Корзина пуста.');
        }

        return DB::transaction(function () use ($user, $items): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'total_amount' => $this->cartService->getTotalFromItems($items),
                'currency' => config('shop.currency_code'),
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'book_id' => $item->book_id,
                    'price' => $item->book->price,
                    'currency' => config('shop.currency_code'),
                ]);
            }

            $order->load('items.book');

            return $order;
        });
    }

    /**
     * Find an order by its provider-specific session ID.
     *
     * Looks up the OrderTransaction by provider + JSON field, then returns the
     * associated Order (optionally scoped to a user). This replaces the former
     * findByStripeSession() which queried stripe_session_id directly on orders.
     */
    public function findByProviderSession(string $provider, string $sessionId, ?int $userId = null): ?Order
    {
        $transaction = $this->findTransactionByProviderData($provider, 'session_id', $sessionId);

        if ($transaction === null) {
            return null;
        }

        $query = Order::query()
            ->with('items.book')
            ->where('id', $transaction->order_id);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    /**
     * Find an OrderTransaction by a specific key within provider_data JSON.
     *
     * Uses json_extract() which is supported by both MySQL 5.7+ and SQLite 3.38+.
     * Avoids JSON_UNQUOTE() because it is MySQL-only and not available in the
     * SQLite in-memory database used during tests.
     *
     * SECURITY: $key is interpolated directly into the SQL expression. It MUST be
     * a trusted internal constant (e.g. 'session_id', 'payment_intent') — never
     * pass user-supplied input as $key.
     */
    /**
     * @throws \InvalidArgumentException if $key is not an allowed JSON field name
     */
    public function findTransactionByProviderData(string $provider, string $key, string $value): ?OrderTransaction
    {
        $allowed = ['session_id', 'transaction_id', 'payment_intent'];

        if (! in_array($key, $allowed, strict: true)) {
            throw new \InvalidArgumentException("JSON key '$key' is not allowed in provider_data lookups.");
        }

        return OrderTransaction::query()
            ->with('order')
            ->where('provider', $provider)
            ->whereRaw("json_extract(provider_data, '$.{$key}') = ?", [$value])
            ->first();
    }
}
