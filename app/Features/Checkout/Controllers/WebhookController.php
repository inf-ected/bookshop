<?php

declare(strict_types=1);

namespace App\Features\Checkout\Controllers;

use App\Features\Checkout\Contracts\SupportsWebhooks;
use App\Features\Checkout\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private readonly SupportsWebhooks $stripeProvider) {}

    /**
     * Handle Stripe webhook events.
     *
     * Rule 35: signature verification is performed inside the provider.
     * Rule 29: webhook is the source of truth for payment confirmation.
     */
    public function handleStripe(Request $request): Response
    {
        try {
            $this->stripeProvider->handleWebhook(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
            );
        } catch (PaymentException $e) {
            Log::warning('Stripe webhook rejected', ['error' => $e->getMessage()]);

            return response($e->getMessage(), 400);
        }

        return response('OK', 200);
    }
}
