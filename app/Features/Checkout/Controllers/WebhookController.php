<?php

declare(strict_types=1);

namespace App\Features\Checkout\Controllers;

use App\Enums\OrderStatus;
use App\Features\Checkout\Jobs\ProcessPaymentConfirmation;
use App\Features\Checkout\Services\OrderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class WebhookController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * Handle Stripe webhook events.
     *
     * Rule 35: Stripe webhook signature is verified on every request.
     * Rule 29: Webhook is the source of truth for payment confirmation.
     * Rule 30: Idempotency via order_transactions — skip if already paid.
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
        } catch (UnexpectedValueException $e) {
            Log::warning('Stripe webhook invalid payload', [
                'error' => $e->getMessage(),
            ]);

            return response('Invalid payload', 400);
        }

        Log::info('Stripe webhook received', ['type' => $event->type]);

        if ($event->type === 'checkout.session.completed') {
            /** @var Session $session */
            $session = $event->data->object;

            $stripeSessionId = $session->id;
            $paymentIntentId = is_string($session->payment_intent)
                ? $session->payment_intent
                : (string) ($session->payment_intent->id ?? '');

            // Look up order via order_transactions (provider-agnostic approach)
            $order = $this->orderService->findByProviderSession('stripe', $stripeSessionId);

            if ($order === null) {
                Log::warning('Stripe webhook: order not found for session', [
                    'session_id' => $stripeSessionId,
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
                    'session_id' => $stripeSessionId,
                ]);

                return response('OK', 200);
            }

            // Rule 30: skip dispatch if already paid — defence-in-depth against duplicate webhooks
            if ($order->status === OrderStatus::Paid) {
                Log::info('Stripe webhook: order already paid, skipping', [
                    'order_id' => $order->id,
                    'session_id' => $stripeSessionId,
                ]);

                return response('OK', 200);
            }

            Log::info('Stripe webhook: dispatching ProcessPaymentConfirmation', [
                'order_id' => $order->id,
                'session_id' => $stripeSessionId,
            ]);

            // Dispatch queued job — Rule 29, 30, 31
            ProcessPaymentConfirmation::dispatch(
                $order->id,
                $paymentIntentId,
                $stripeSessionId,
            );
        }

        if ($event->type === 'checkout.session.expired') {
            /** @var Session $session */
            $session = $event->data->object;

            $stripeSessionId = $session->id;

            $transaction = $this->orderService->findTransactionByProviderData('stripe', 'session_id', $stripeSessionId);

            if ($transaction === null) {
                Log::warning('Stripe webhook: transaction not found for expired session', [
                    'session_id' => $stripeSessionId,
                ]);

                return response('OK', 200);
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

        return response('OK', 200);
    }
}
