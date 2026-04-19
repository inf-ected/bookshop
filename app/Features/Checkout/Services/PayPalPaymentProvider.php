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
use App\Models\OrderTransaction;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * PayPal REST API v2 payment provider.
 *
 * ## HTTP request flow (5 outbound calls per successful purchase)
 *
 * ### 1. Checkout initiated — createSession()
 *   [1] POST /v1/oauth2/token            client_credentials grant → access token (cached 9h)
 *   [2] POST /v2/checkout/orders         create order → { id, links[approve] }
 *       → OrderTransaction stored { session_id: paypal_order_id, status: pending }
 *       → redirect customer to PayPal approval URL
 *
 * ### 2. Customer approves on PayPal → redirect back to /checkout/success?token=ORDER_ID
 *       handleReturn()
 *   [3] POST /v2/checkout/orders/{id}/capture   lock funds server-side
 *       → order stays pending (Rule 29: webhook is source of truth)
 *       → customer sees polling page
 *
 * ### 3. PayPal sends PAYMENT.CAPTURE.COMPLETED webhook → /webhooks/paypal
 *       handleWebhook() → verifyWebhookSignature()
 *   [4] POST /v1/oauth2/token            (or from cache)
 *   [5] POST /v1/notifications/verify-webhook-signature   asymmetric RSA signature check
 *       → { verification_status: "SUCCESS" }
 *       → dispatch ProcessPaymentConfirmation → order paid, books granted
 *
 * ### Why webhook verification requires an extra API call (vs Stripe's local HMAC)
 *   PayPal uses asymmetric RSA+SHA256 signatures with a rotating certificate (paypal-cert-url
 *   header). Local verification is possible but requires fetching the cert and doing RSA crypto.
 *   PayPal's verification API accepts the raw headers + payload and returns SUCCESS/FAILURE —
 *   simpler, no crypto code, one extra round-trip per webhook (acceptable given webhook rarity).
 */
readonly class PayPalPaymentProvider implements PaymentProvider, SupportsWebhooks
{
    private string $baseUrl;

    /**
     * @throws RuntimeException if required config values are missing.
     * @throws Throwable
     */
    public function __construct(private OrderService $orderService)
    {
        $clientId = config('services.paypal.client_id');
        $clientSecret = config('services.paypal.client_secret');

        throw_if(empty($clientId), RuntimeException::class, 'PAYPAL_CLIENT_ID is not configured.');
        throw_if(empty($clientSecret), RuntimeException::class, 'PAYPAL_CLIENT_SECRET is not configured.');

        $this->baseUrl = config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function getName(): PaymentGateway
    {
        return PaymentGateway::PayPal;
    }

    /**
     * Extract the PayPal order token from the return redirect.
     * PayPal appends `?token=<order_id>` to the return URL.
     */
    public function extractReturnSessionId(Request $request): ?string
    {
        $value = $request->query('token');

        return is_string($value) ? $value : null;
    }

    /**
     * Trigger the PayPal capture API call on the success redirect.
     *
     * PayPal requires an explicit capture to lock in the funds — unlike Stripe which
     * settles asynchronously. The capture is initiated here so funds are secured
     * before the user reaches the polling page. Order status is NOT updated by this
     * method; it remains pending until the PAYMENT.CAPTURE.COMPLETED webhook fires
     * and ProcessPaymentConfirmation runs (Rule 29).
     *
     * @throws PaymentException
     * @throws ConnectionException
     */
    public function handleReturn(Request $request, Order $order): void
    {
        // Guard: order already paid (e.g. user refreshed the success page after webhook fired)
        if ($order->status === OrderStatus::Paid) {
            return;
        }

        $token = $this->extractReturnSessionId($request);

        if ($token === null) {
            return;
        }

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/v2/checkout/orders/{$token}/capture");

        if ($response->failed()) {
            Log::error('PayPal capture failed on return redirect', [
                'order_id' => $order->id,
                'token' => $token,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new PaymentException('PayPal capture failed: '.$response->json('message', 'Unknown error'));
        }

        Log::info('PayPal order captured on return redirect', [
            'order_id' => $order->id,
            'token' => $token,
        ]);
    }

    /**
     * Create a PayPal Checkout order and return the approval URL.
     *
     * @return array{id: string, url: string}
     *
     * @throws PaymentException
     * @throws ConnectionException
     */
    public function createSession(Order $order, User $user): array
    {
        if (! $order->relationLoaded('items')) {
            $order->load('items.book');
        }

        $currency = strtoupper($order->currency);
        $totalAmount = number_format($order->total_amount / 100, 2, '.', '');

        $items = $order->items->map(function ($item) use ($currency): array {
            return [
                'name' => $item->book->title,
                'quantity' => '1',
                'unit_amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($item->price / 100, 2, '.', ''),
                ],
            ];
        })->values()->all();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => (string) $order->id,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $totalAmount,
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => $currency,
                                'value' => $totalAmount,
                            ],
                        ],
                    ],
                    'items' => $items,
                ],
            ],
            'application_context' => [
                'return_url' => route('checkout.success').'?provider='.PaymentGateway::PayPal->value,
                'cancel_url' => route('cart.index'),
                'brand_name' => config('app.name'),
                'user_action' => 'PAY_NOW',
            ],
        ];

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/v2/checkout/orders", $payload);

        if ($response->failed()) {
            Log::error('PayPal order creation failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new PaymentException('PayPal order creation failed: '.$response->json('message', 'Unknown error'));
        }

        $data = $response->json();
        $paypalOrderId = $data['id'] ?? null;

        if ($paypalOrderId === null) {
            throw new PaymentException('PayPal did not return an order ID.');
        }

        // Find the approval URL from the links array
        $approvalUrl = null;
        foreach ($data['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }

        if ($approvalUrl === null) {
            throw new PaymentException('PayPal did not return an approval URL for order '.$paypalOrderId);
        }

        OrderTransaction::query()->create([
            'order_id' => $order->id,
            'provider' => PaymentGateway::PayPal->value,
            'provider_data' => [
                'session_id' => $paypalOrderId,
                'paypal_order_id' => $paypalOrderId,
            ],
            'status' => 'pending',
            'expires_at' => Carbon::now()->addHours(3),
        ]);

        return [
            'id' => $paypalOrderId,
            'url' => $approvalUrl,
        ];
    }

    /**
     * Handle an incoming PayPal webhook event.
     *
     * Rule 35: Verifies the webhook signature using PayPal's verification API.
     * Rule 29: Webhook is the authoritative source of truth for payment confirmation.
     * Rule 30: Idempotency — skip if order already paid.
     *
     * @param  array<string, string|string[]>  $headers
     *
     * @throws PaymentException on signature verification failure or invalid payload
     * @throws ConnectionException
     */
    public function handleWebhook(string $payload, array $headers): void
    {
        $this->verifyWebhookSignature($payload, $headers);

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true);
        $eventType = $event['event_type'] ?? null;

        Log::info('PayPal webhook received', ['event_type' => $eventType]);

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $this->handleCaptureCompleted($event);
        }
    }

    /**
     * Process a PAYMENT.CAPTURE.COMPLETED event.
     * Dispatches ProcessPaymentConfirmation — same job used by Stripe.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleCaptureCompleted(array $event): void
    {
        /** @var array<string, mixed> $resource */
        $resource = $event['resource'] ?? [];

        // The supplementary_data.related_ids.order_id holds the PayPal order ID
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id']
            ?? null;

        // Fallback: some events carry the order id in links
        if ($paypalOrderId === null) {
            foreach ($resource['links'] ?? [] as $link) {
                if ($link['rel'] === 'up') {
                    // e.g. https://api-m.sandbox.paypal.com/v2/checkout/orders/{id}
                    $parts = explode('/', rtrim($link['href'], '/'));
                    $paypalOrderId = end($parts) ?: null;
                    break;
                }
            }
        }

        if ($paypalOrderId === null) {
            Log::warning('PayPal webhook: could not extract order ID from PAYMENT.CAPTURE.COMPLETED event');

            return;
        }

        $captureId = (string) ($resource['id'] ?? '');

        $order = $this->orderService->findByProviderSession(PaymentGateway::PayPal->value, $paypalOrderId);

        if ($order === null) {
            Log::warning('PayPal webhook: order not found for PayPal order', [
                'paypal_order_id' => $paypalOrderId,
            ]);

            return;
        }

        // Rule 30: idempotency
        if ($order->status === OrderStatus::Paid) {
            Log::info('PayPal webhook: order already paid, skipping', [
                'order_id' => $order->id,
                'paypal_order_id' => $paypalOrderId,
            ]);

            return;
        }

        Log::info('PayPal webhook: dispatching ProcessPaymentConfirmation', [
            'order_id' => $order->id,
            'paypal_order_id' => $paypalOrderId,
        ]);

        ProcessPaymentConfirmation::dispatch(
            $order->id,
            $captureId,
            $paypalOrderId,
            $this->getName(),
        );
    }

    /**
     * Verify the PayPal webhook signature using the PayPal verification API.
     *
     * @param  array<string, string|string[]>  $headers
     *
     * @throws PaymentException on verification failure.
     * @throws ConnectionException
     */
    private function verifyWebhookSignature(string $payload, array $headers): void
    {
        $webhookId = config('services.paypal.webhook_id');

        if (empty($webhookId)) {
            throw new PaymentException('PAYPAL_WEBHOOK_ID is not configured.');
        }

        $extractHeader = function (string $key) use ($headers): string {
            $value = $headers[strtolower($key)] ?? $headers[$key] ?? '';

            return is_array($value) ? implode(',', $value) : (string) $value;
        };

        $transmissionId = $extractHeader('paypal-transmission-id');
        $transmissionTime = $extractHeader('paypal-transmission-time');
        $certUrl = $extractHeader('paypal-cert-url');
        $authAlgo = $extractHeader('paypal-auth-algo');
        $transmissionSig = $extractHeader('paypal-transmission-sig');

        if ($transmissionId === '' || $transmissionSig === '') {
            throw new PaymentException('PayPal webhook is missing required signature headers.');
        }

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", [
                'transmission_id' => $transmissionId,
                'transmission_time' => $transmissionTime,
                'cert_url' => $certUrl,
                'auth_algo' => $authAlgo,
                'transmission_sig' => $transmissionSig,
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($payload, true),
            ]);

        if ($response->failed()) {
            throw new PaymentException('PayPal webhook signature verification request failed.');
        }

        $verificationStatus = $response->json('verification_status');

        if ($verificationStatus !== 'SUCCESS') {
            throw new PaymentException('PayPal webhook signature verification failed: '.$verificationStatus);
        }
    }

    /**
     * Obtain a cached OAuth2 access token using client_credentials grant.
     *
     * Tokens are cached in Redis with a TTL slightly shorter than the PayPal token
     * expiry (typically 32400 seconds / 9 hours) to avoid using an expired token.
     *
     * @throws PaymentException if the token request fails.
     * @throws ConnectionException
     */
    private function getAccessToken(): string
    {
        $cacheKey = 'paypal_access_token_'.config('services.paypal.mode', 'sandbox');

        /** @var string|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $clientId = (string) config('services.paypal.client_id');
        $clientSecret = (string) config('services.paypal.client_secret');

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new PaymentException('Failed to obtain PayPal access token.');
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new PaymentException('PayPal returned an empty or missing access token.');
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 32400);

        // Cache with a 60-second safety margin
        Cache::put($cacheKey, $token, $expiresIn - 60);

        return $token;
    }
}
