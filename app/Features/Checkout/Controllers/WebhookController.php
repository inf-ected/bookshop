<?php

declare(strict_types=1);

namespace App\Features\Checkout\Controllers;

use App\Features\Checkout\Exceptions\PaymentException;
use App\Features\Checkout\Services\PaymentProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WebhookController extends Controller
{
    public function __construct(private readonly PaymentProviderRegistry $registry) {}

    /**
     * Handle incoming webhook events from any registered payment provider.
     *
     * Rule 35: signature verification is performed inside each provider.
     * Rule 29: webhook is the source of truth for payment confirmation.
     */
    public function handle(Request $request, string $provider): Response
    {
        try {
            $webhookHandler = $this->registry->webhookProvider($provider);
        } catch (InvalidArgumentException $e) {
            Log::warning('Webhook received for unknown provider', ['provider' => Str::limit($provider, 50)]);

            return response('OK', 200);
        }

        try {
            $webhookHandler->handleWebhook(
                $request->getContent(),
                $request->headers->all(),
            );
        } catch (PaymentException $e) {
            Log::warning('Webhook rejected', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response($e->getMessage(), 400);
        }

        return response('OK', 200);
    }
}
