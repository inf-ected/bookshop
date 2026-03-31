<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentConfirmation;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class WebhookController extends Controller
{
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

        if ($event->type === 'checkout.session.completed') {
            /** @var Session $session */
            $session = $event->data->object;

            $stripeSessionId = $session->id;
            $paymentIntentId = is_string($session->payment_intent)
                ? $session->payment_intent
                : (string) ($session->payment_intent->id ?? '');

            // Rule 30: look up order by stripe_session_id for idempotency
            $order = Order::query()
                ->where('stripe_session_id', $stripeSessionId)
                ->first();

            if ($order === null) {
                Log::warning('Stripe webhook: order not found for session', [
                    'stripe_session_id' => $stripeSessionId,
                ]);

                return response('Order not found', 200);
            }

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
