<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentProvider;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripePaymentProvider implements PaymentProvider
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe Checkout session for the given order.
     *
     * @return array{id: string, url: string}
     *
     * @throws ApiErrorException
     */
    public function createSession(Order $order, User $user): array
    {

        if (! $order->relationLoaded('items')) {
            $order->load('items.book');
        }

        $lineItems = $order->items->map(function (OrderItem $item): array {
            return [
                'price_data' => [
                    'currency' => 'rub',
                    'unit_amount' => $item->price,
                    'product_data' => [
                        'name' => $item->book->title,
                    ],
                ],
                'quantity' => 1,
            ];
        })->values()->all();

        $session = Session::create([
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('cart.index'),
            'client_reference_id' => (string) $order->id,
            'customer_email' => $user->email,
        ]);

        if ($session->url === null) {
            throw new \RuntimeException('Stripe did not return a checkout URL for session '.$session->id);
        }

        return [
            'id' => $session->id,
            'url' => $session->url,
        ];
    }
}
