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
     * Returns void — HTTP response is the controller's responsibility.
     *
     * @throws PaymentException on signature verification failure or invalid payload
     * @throws \Throwable on unexpected errors (causes provider retry via HTTP 500)
     */
    public function handleWebhook(string $payload, string $signature): void;
}
