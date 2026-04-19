<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Checkout\Exceptions\PaymentException;
use App\Features\Checkout\Jobs\ProcessPaymentConfirmation;
use App\Features\Checkout\Services\PayPalPaymentProvider;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PayPalPaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    private PayPalPaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paypal.client_id' => 'test_client_id',
            'services.paypal.client_secret' => 'test_client_secret',
            'services.paypal.webhook_id' => 'test_webhook_id',
            'services.paypal.mode' => 'sandbox',
        ]);

        $this->provider = $this->app->make(PayPalPaymentProvider::class);
    }

    // -------------------------------------------------------------------------
    // getName()
    // -------------------------------------------------------------------------

    public function test_get_name_returns_paypal(): void
    {
        $this->assertSame('paypal', $this->provider->getName());
    }

    // -------------------------------------------------------------------------
    // extractReturnSessionId()
    // -------------------------------------------------------------------------

    public function test_extract_return_session_id_reads_token_query_param(): void
    {
        $request = Request::create('/checkout/success?token=PAYPAL-ORDER-123');

        $this->assertSame('PAYPAL-ORDER-123', $this->provider->extractReturnSessionId($request));
    }

    public function test_extract_return_session_id_returns_null_when_token_missing(): void
    {
        $request = Request::create('/checkout/success');

        $this->assertNull($this->provider->extractReturnSessionId($request));
    }

    // -------------------------------------------------------------------------
    // createSession()
    // -------------------------------------------------------------------------

    public function test_create_session_returns_id_and_approval_url(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'fake_access_token',
                'expires_in' => 32400,
            ], 200),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-ABC123',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-ABC123'],
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-ABC123'],
                    ['rel' => 'update', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-ABC123'],
                    ['rel' => 'capture', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-ABC123/capture'],
                ],
            ], 201),
        ]);

        $user = User::factory()->create();
        $book = Book::factory()->create(['price' => 59000]);
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
            'total_amount' => 59000,
        ]);
        $order->items()->create([
            'book_id' => $book->id,
            'price' => 59000,
            'currency' => 'RUB',
        ]);
        $order->load('items.book');

        $result = $this->provider->createSession($order, $user);

        $this->assertSame('PAYPAL-ORDER-ABC123', $result['id']);
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-ABC123', $result['url']);

        $this->assertDatabaseHas('order_transactions', [
            'order_id' => $order->id,
            'provider' => 'paypal',
            'status' => 'pending',
        ]);
    }

    public function test_create_session_throws_on_api_failure(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'fake_access_token',
                'expires_in' => 32400,
            ], 200),
            '*/v2/checkout/orders' => Http::response(['message' => 'Bad Request'], 400),
        ]);

        $user = User::factory()->create();
        $book = Book::factory()->create(['price' => 59000]);
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
            'total_amount' => 59000,
        ]);
        $order->items()->create([
            'book_id' => $book->id,
            'price' => 59000,
            'currency' => 'RUB',
        ]);
        $order->load('items.book');

        $this->expectException(PaymentException::class);

        $this->provider->createSession($order, $user);
    }

    // -------------------------------------------------------------------------
    // handleReturn()
    // -------------------------------------------------------------------------

    public function test_handle_return_calls_capture_endpoint(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'fake_access_token',
                'expires_in' => 32400,
            ], 200),
            '*/v2/checkout/orders/PAYPAL-ORDER-ABC123/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-ABC123',
                'status' => 'COMPLETED',
            ], 201),
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);

        $request = Request::create('/checkout/success?token=PAYPAL-ORDER-ABC123');

        // Should not throw
        $this->provider->handleReturn($request, $order);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/v2/checkout/orders/PAYPAL-ORDER-ABC123/capture');
        });
    }

    public function test_handle_return_throws_on_capture_failure(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'fake_access_token',
                'expires_in' => 32400,
            ], 200),
            '*/v2/checkout/orders/PAYPAL-ORDER-FAIL/capture' => Http::response([
                'message' => 'UNPROCESSABLE_ENTITY',
            ], 422),
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);

        $request = Request::create('/checkout/success?token=PAYPAL-ORDER-FAIL');

        $this->expectException(PaymentException::class);

        $this->provider->handleReturn($request, $order);
    }

    public function test_handle_return_is_noop_when_no_token(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);

        $request = Request::create('/checkout/success');

        // Should not throw and should not make any HTTP requests
        $this->provider->handleReturn($request, $order);

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // handleWebhook() — happy path (Rule 29)
    // -------------------------------------------------------------------------

    public function test_handle_webhook_fires_process_payment_confirmation_on_capture_completed(): void
    {
        Queue::fake();

        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'fake_access_token',
                'expires_in' => 32400,
            ], 200),
            '*/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);

        $user = User::factory()->create();
        $book = Book::factory()->create(['price' => 59000]);
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
            'total_amount' => 59000,
        ]);
        OrderTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'provider' => 'paypal',
            'provider_data' => ['session_id' => 'PAYPAL-ORDER-XYZ789'],
        ]);
        $order->items()->create([
            'book_id' => $book->id,
            'price' => 59000,
            'currency' => 'RUB',
        ]);

        $payload = json_encode($this->buildCaptureCompletedEvent('CAPTURE-ID-001', 'PAYPAL-ORDER-XYZ789'));
        $headers = $this->buildWebhookHeaders();

        $this->provider->handleWebhook($payload, $headers);

        Queue::assertPushed(ProcessPaymentConfirmation::class, function ($job) use ($order): bool {
            return $job->orderId === $order->id
                && $job->sessionId === 'PAYPAL-ORDER-XYZ789'
                && $job->provider === 'paypal';
        });
    }

    // -------------------------------------------------------------------------
    // handleWebhook() — idempotency (Rule 30)
    // -------------------------------------------------------------------------

    public function test_handle_webhook_skips_job_if_order_already_paid(): void
    {
        Queue::fake();

        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'fake_access_token',
                'expires_in' => 32400,
            ], 200),
            '*/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->paid()->create(['user_id' => $user->id]);
        OrderTransaction::factory()->succeeded()->create([
            'order_id' => $order->id,
            'provider' => 'paypal',
            'provider_data' => ['session_id' => 'PAYPAL-ORDER-ALREADY-PAID'],
        ]);

        $payload = json_encode($this->buildCaptureCompletedEvent('CAPTURE-ID-002', 'PAYPAL-ORDER-ALREADY-PAID'));
        $headers = $this->buildWebhookHeaders();

        $this->provider->handleWebhook($payload, $headers);

        Queue::assertNotPushed(ProcessPaymentConfirmation::class);
    }

    // -------------------------------------------------------------------------
    // handleWebhook() — signature verification (Rule 35)
    // -------------------------------------------------------------------------

    public function test_handle_webhook_throws_on_failed_signature_verification(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'fake_access_token',
                'expires_in' => 32400,
            ], 200),
            '*/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'FAILURE',
            ], 200),
        ]);

        $payload = json_encode($this->buildCaptureCompletedEvent('CAPTURE-ID-003', 'PAYPAL-ORDER-BAD'));
        $headers = $this->buildWebhookHeaders();

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/verification failed/i');

        $this->provider->handleWebhook($payload, $headers);
    }

    public function test_handle_webhook_throws_when_signature_headers_missing(): void
    {
        $payload = json_encode($this->buildCaptureCompletedEvent('CAPTURE-ID-004', 'PAYPAL-ORDER-NO-SIG'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/missing required signature headers/i');

        $this->provider->handleWebhook($payload, []);
    }

    // -------------------------------------------------------------------------
    // handleWebhook() — unknown order (graceful handling)
    // -------------------------------------------------------------------------

    public function test_handle_webhook_returns_silently_when_order_not_found(): void
    {
        Queue::fake();

        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'fake_access_token',
                'expires_in' => 32400,
            ], 200),
            '*/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);

        $payload = json_encode($this->buildCaptureCompletedEvent('CAPTURE-ID-005', 'PAYPAL-ORDER-UNKNOWN'));
        $headers = $this->buildWebhookHeaders();

        // Should not throw — log warning and return
        $this->provider->handleWebhook($payload, $headers);

        Queue::assertNotPushed(ProcessPaymentConfirmation::class);
    }

    // -------------------------------------------------------------------------
    // Webhook route — generic handler resolves 'paypal'
    // -------------------------------------------------------------------------

    public function test_paypal_webhook_route_resolves_provider_and_returns_200(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'fake_access_token',
                'expires_in' => 32400,
            ], 200),
            '*/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);

        $payload = json_encode([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [],
        ]);

        $response = $this->call(
            'POST',
            route('webhooks.handle', ['provider' => 'paypal']),
            [],
            [],
            [],
            array_merge(
                ['CONTENT_TYPE' => 'application/json'],
                $this->buildServerHeaders($this->buildWebhookHeaders()),
            ),
            $payload,
        );

        $response->assertStatus(200);
    }

    public function test_webhook_route_returns_200_for_unknown_provider(): void
    {
        // Unknown providers get a silent 200 — no retry storm from the sender,
        // no information leak about internal provider registry.
        $response = $this->call(
            'POST',
            route('webhooks.handle', ['provider' => 'unknown_provider']),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a PAYMENT.CAPTURE.COMPLETED event payload.
     *
     * @return array<string, mixed>
     */
    private function buildCaptureCompletedEvent(string $captureId, string $paypalOrderId): array
    {
        return [
            'id' => 'WH-'.fake()->regexify('[A-Z0-9]{17}'),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => $captureId,
                'status' => 'COMPLETED',
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => $paypalOrderId,
                    ],
                ],
                'links' => [
                    ['rel' => 'self', 'href' => "https://api-m.sandbox.paypal.com/v2/payments/captures/{$captureId}"],
                    ['rel' => 'up', 'href' => "https://api-m.sandbox.paypal.com/v2/checkout/orders/{$paypalOrderId}"],
                ],
            ],
        ];
    }

    /**
     * Build the PayPal webhook headers array (as passed by the WebhookController).
     *
     * @return array<string, string>
     */
    private function buildWebhookHeaders(): array
    {
        return [
            'paypal-transmission-id' => fake()->uuid(),
            'paypal-transmission-time' => now()->toIso8601String(),
            'paypal-cert-url' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-123',
            'paypal-auth-algo' => 'SHA256withRSA',
            'paypal-transmission-sig' => base64_encode('fake_signature'),
        ];
    }

    /**
     * Convert lower-case header keys to PHP $_SERVER format (HTTP_*) for use with $this->call().
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function buildServerHeaders(array $headers): array
    {
        $server = [];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $server;
    }
}
