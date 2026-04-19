<?php

declare(strict_types=1);

namespace App\Features\Checkout\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Features\Checkout\Contracts\PaymentProvider;
use App\Features\Checkout\Contracts\SupportsWebhooks;
use App\Features\Checkout\Exceptions\PaymentException;
use App\Features\Checkout\Jobs\ProcessPaymentConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

readonly class StripePaymentProvider implements PaymentProvider, SupportsWebhooks
{
    /**
     * @throws RuntimeException if STRIPE_SECRET or STRIPE_WEBHOOK_SECRET are not configured
     */
    public function __construct(private OrderService $orderService)
    {
        $secret = config('services.stripe.secret');
        $webhookSecret = config('services.stripe.webhook_secret');

        throw_if(empty($secret), RuntimeException::class, 'STRIPE_SECRET is not configured.');
        throw_if(empty($webhookSecret), RuntimeException::class, 'STRIPE_WEBHOOK_SECRET is not configured.');

        Stripe::setApiKey($secret);
    }

    public function getName(): PaymentGateway
    {
        return PaymentGateway::Stripe;
    }

    public function extractReturnSessionId(Request $request): ?string
    {
        $value = $request->query('session_id');

        return is_string($value) ? $value : null;
    }

    /**
     * No-op for Stripe — payment confirmation arrives via webhook, not on the return redirect.
     */
    public function handleReturn(Request $request, Order $order): void {}

    /**
     * Create a Stripe Checkout session for the given order and persist an
     * OrderTransaction record with all provider-specific data.
     *
     * @return array{id: string, url: string}
     *
     * @throws PaymentException
     */
    public function createSession(Order $order, User $user): array
    {
        if (! $order->relationLoaded('items')) {
            $order->load('items.book');
        }

        $currency = strtolower($order->currency);

        $lineItems = $order->items->map(function (OrderItem $item) use ($currency): array {
            return [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $item->price,
                    'product_data' => [
                        'name' => $item->book->title,
                    ],
                ],
                'quantity' => 1,
            ];
        })->values()->all();

        try {
            $session = Session::create([
                'mode' => 'payment',
                'line_items' => $lineItems,
                'success_url' => route('checkout.success').'?provider='.PaymentGateway::Stripe->value.'&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('cart.index'),
                'client_reference_id' => (string) $order->id,
                'customer_email' => $user->email,
            ]);
        } catch (ApiErrorException $e) {
            throw PaymentException::fromThrowable($e);
        }

        if ($session->url === null) {
            throw new PaymentException('Stripe did not return a checkout URL for session '.$session->id);
        }

        // Persist provider-specific data in order_transactions so it never leaks
        // into the orders table (business entity stays provider-agnostic).
        OrderTransaction::query()->create([
            'order_id' => $order->id,
            'provider' => PaymentGateway::Stripe->value,
            'provider_data' => [
                'session_id' => $session->id,
                'transaction_id' => $session->payment_intent,
            ],
            'status' => 'pending',
            'expires_at' => Carbon::createFromTimestamp($session->expires_at),
        ]);

        return [
            'id' => $session->id,
            'url' => $session->url,
        ];
    }

    /**
     * Handle an incoming Stripe webhook event.
     *
     * Rule 35: signature is verified on every request.
     * Rule 29: webhook is the source of truth for payment confirmation.
     * Rule 30: idempotency via order_transactions — skip if already paid.
     *
     * @throws PaymentException on signature verification failure or invalid payload
     */
    public function handleWebhook(string $payload, array $headers): void
    {
        $signature = is_array($headers['stripe-signature'] ?? null)
            ? implode(',', $headers['stripe-signature'])
            : (string) ($headers['stripe-signature'] ?? '');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw PaymentException::fromThrowable($e);
        }

        Log::info('Stripe webhook received', ['type' => $event->type]);

        if ($event->type === 'checkout.session.completed') {
            /** @var Session $session */
            $session = $event->data->object;
            $this->handleSessionCompleted($session);
        }

        if ($event->type === 'checkout.session.expired') {
            /** @var Session $session */
            $session = $event->data->object;
            $this->handleSessionExpired($session);
        }
    }

    /**
     * Process a completed Stripe Checkout session.
     * Dispatches ProcessPaymentConfirmation when payment is confirmed.
     */
    private function handleSessionCompleted(Session $session): void
    {
        $stripeSessionId = $session->id;
        $transactionId = is_string($session->payment_intent)
            ? $session->payment_intent
            : (string) ($session->payment_intent->id ?? '');

        // Look up order via order_transactions (provider-agnostic approach)
        $order = $this->orderService->findByProviderSession(PaymentGateway::Stripe->value, $stripeSessionId);

        if ($order === null) {
            Log::warning('Stripe webhook: order not found for session', [
                'session_id' => $stripeSessionId,
            ]);

            return;
        }

        // checkout.session.completed fires for all payment methods, including async ones
        // (bank transfers, OXXO, etc.) where payment_status is still 'unpaid' at the time
        // the session completes. For those, funds arrive later via async_payment_succeeded.
        // We only grant books when payment is confirmed — skip anything not yet paid.
        if ($session->payment_status !== 'paid') {
            Log::info('Stripe webhook: session completed but payment not yet confirmed', [
                'order_id' => $order->id,
                'payment_status' => $session->payment_status,
                'session_id' => $stripeSessionId,
            ]);

            return;
        }

        // Rule 30: skip dispatch if already paid — defence-in-depth against duplicate webhooks
        if ($order->status === OrderStatus::Paid) {
            Log::info('Stripe webhook: order already paid, skipping', [
                'order_id' => $order->id,
                'session_id' => $stripeSessionId,
            ]);

            return;
        }

        Log::info('Stripe webhook: dispatching ProcessPaymentConfirmation', [
            'order_id' => $order->id,
            'session_id' => $stripeSessionId,
        ]);

        // Dispatch queued job — Rule 29, 30, 31
        ProcessPaymentConfirmation::dispatch(
            $order->id,
            $transactionId,
            $stripeSessionId,
            $this->getName(),
        );
    }

    /**
     * Process an expired Stripe Checkout session.
     * Marks the transaction and order as failed if still pending.
     *
     * @throws Throwable
     */
    private function handleSessionExpired(Session $session): void
    {
        $stripeSessionId = $session->id;

        $transaction = $this->orderService->findTransactionByProviderData(PaymentGateway::Stripe->value, 'session_id', $stripeSessionId);

        if ($transaction === null) {
            Log::warning('Stripe webhook: transaction not found for expired session', [
                'session_id' => $stripeSessionId,
            ]);

            return;
        }

        // Only transition pending transactions — avoid overwriting already-settled states
        if ($transaction->status === 'pending') {
            DB::transaction(function () use ($transaction, $stripeSessionId): void {
                $transaction->status = 'expired';
                $transaction->save();

                $order = $transaction->order;
                if ($order !== null && $order->status === OrderStatus::Pending) {
                    $order->status = OrderStatus::Failed;
                    $order->save();

                    Log::info('Stripe webhook: session expired, order marked failed', [
                        'order_id' => $order->id,
                        'session_id' => $stripeSessionId,
                    ]);
                }
            });
        }
    }
}
