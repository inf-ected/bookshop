<?php

declare(strict_types=1);

namespace App\Features\Checkout\Services;

use App\Enums\OrderStatus;
use App\Features\Cart\Exceptions\EmptyCartException;
use App\Features\Cart\Services\CartService;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly CartService $cartService) {}

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
                'currency' => 'RUB',
                'payment_provider' => 'stripe',
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'book_id' => $item->book_id,
                    'price' => $item->book->price,
                    'currency' => 'RUB',
                ]);
            }

            $order->load('items.book');

            return $order;
        });
    }

    public function findByStripeSession(string $stripeSessionId, ?int $userId = null): ?Order
    {
        $query = Order::query()->where('stripe_session_id', $stripeSessionId);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }
}
