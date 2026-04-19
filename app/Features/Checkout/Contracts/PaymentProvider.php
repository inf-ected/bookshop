<?php

declare(strict_types=1);

namespace App\Features\Checkout\Contracts;

use App\Enums\PaymentGateway;
use App\Features\Checkout\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

interface PaymentProvider
{
    /**
     * Provider identifier used for DB storage and routing.
     */
    public function getName(): PaymentGateway;

    /**
     * Create a payment session for the given order and user.
     * Returns the provider session ID and redirect URL.
     *
     * @return array{id: string, url: string}
     *
     * @throws PaymentException
     */
    public function createSession(Order $order, User $user): array;

    /**
     * Extract the provider-specific session identifier from the return redirect request.
     * Stripe: reads `session_id` query param.
     * PayPal: reads `token` query param.
     */
    public function extractReturnSessionId(Request $request): ?string;

    /**
     * Handle any provider-side action required on the success redirect.
     * Providers like PayPal must capture the payment here.
     * Stripe: no-op (payment is confirmed via webhook).
     *
     * @throws PaymentException
     */
    public function handleReturn(Request $request, Order $order): void;
}
