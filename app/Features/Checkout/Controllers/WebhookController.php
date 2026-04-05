<?php

declare(strict_types=1);

namespace App\Features\Checkout\Controllers;

use App\Enums\OrderStatus;
use App\Features\Checkout\Jobs\ProcessPaymentConfirmation;
use App\Features\Checkout\Services\OrderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class WebhookController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * Handle Stripe webhook events.
     *
     * Rule 35: Stripe webhook signature is verified on every request.
     * Rule 29: Webhook is the source of truth for payment confirmation.
     * Rule 30: Idempotency via stripe_session_id — skip if already paid.
     */
    public function handleStripe(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $webhookSecret = config('services.stripe.webhook_secret');

        // Rule 35: verify webhook signature
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response('Signature verification failed', 400);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook invalid payload', [
                'error' => $e->getMessage(),
            ]);

            return response('Invalid payload', 400);
        }

        Log::info('Stripe webhook received', ['type' => $event->type]);

        // We intentionally listen only to checkout.session.completed.
        // Stripe also sends charge.succeeded and payment_intent.succeeded for the same
        // transaction, but checkout.session.completed is the only event that carries our
        // stripe_session_id, which is the key we use to look up the order.
        if ($event->type === 'checkout.session.completed') {
            /** @var Session $session */
            $session = $event->data->object;

            $stripeSessionId = $session->id;
            $paymentIntentId = is_string($session->payment_intent)
                ? $session->payment_intent
                : (string) ($session->payment_intent->id ?? '');

            // Rule 30: look up order by stripe_session_id for idempotency
            $order = $this->orderService->findByStripeSession($stripeSessionId);

            if ($order === null) {
                Log::warning('Stripe webhook: order not found for session', [
                    'stripe_session_id' => $stripeSessionId,
                ]);

                return response('Order not found', 200);
            }

            // checkout.session.completed fires for all payment methods, including async ones
            // (bank transfers, OXXO, etc.) where payment_status is still 'unpaid' at the time
            // the session completes. For those, funds arrive later via async_payment_succeeded.
            // We only grant books when payment is confirmed — skip anything not yet paid.
            if ($session->payment_status !== 'paid') {
                Log::info('Stripe webhook: session completed but payment not yet confirmed', [
                    'order_id' => $order->id,
                    'payment_status' => $session->payment_status,
                    'stripe_session_id' => $stripeSessionId,
                ]);

                return response('OK', 200);
            }

            // Rule 30: skip dispatch if already paid — defence-in-depth against duplicate webhooks
            if ($order->status === OrderStatus::Paid) {
                Log::info('Stripe webhook: order already paid, skipping', [
                    'order_id' => $order->id,
                    'stripe_session_id' => $stripeSessionId,
                ]);

                return response('OK', 200);
            }

            Log::info('Stripe webhook: dispatching ProcessPaymentConfirmation', [
                'order_id' => $order->id,
                'stripe_session_id' => $stripeSessionId,
            ]);

            // Dispatch queued job — Rule 29, 30, 31
            ProcessPaymentConfirmation::dispatch(
                $order->id,
                $paymentIntentId,
                $stripeSessionId,
            );
        }

        return response('OK', 200);
    }
}
