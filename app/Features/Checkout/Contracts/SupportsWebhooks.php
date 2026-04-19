<?php

declare(strict_types=1);

namespace App\Features\Checkout\Contracts;

use App\Features\Checkout\Exceptions\PaymentException;

interface SupportsWebhooks
{
    /**
     * Handle an incoming webhook from the payment provider.
     *
     * Implementations must verify the payload signature before processing.
     * The full request headers are passed so each provider can extract
     * whatever signature headers it requires (e.g. Stripe-Signature,
     * PayPal-Transmission-Sig + PayPal-Transmission-Id, etc.).
     * Returns void — HTTP response is the controller's responsibility.
     *
     * @param  array<string, string|string[]>  $headers
     *
     * @throws PaymentException on signature verification failure or invalid payload
     * @throws \Throwable on unexpected errors (causes provider retry via HTTP 500)
     */
    public function handleWebhook(string $payload, array $headers): void;
}
