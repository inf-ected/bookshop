<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Features\Checkout\Jobs\ProcessPaymentConfirmation;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        // Use test credentials so StripePaymentProvider passes its config guards
        config([
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.webhook_secret' => $this->webhookSecret,
        ]);
    }

    /**
     * Build a Stripe-signed webhook request payload and signature header.
     *
     * @param  array<string, mixed>  $eventData
     * @return array{payload: string, signature: string}
     */
    private function buildStripeWebhook(array $eventData): array
    {
        $payload = json_encode($eventData);
        $timestamp = time();

        $signedPayload = $timestamp.'.'.$payload;
        // Stripe SDK uses the secret as-is in hash_hmac (no prefix stripping)
        $signature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        $signatureHeader = "t={$timestamp},v1={$signature}";

        return [
            'payload' => $payload,
            'signature' => $signatureHeader,
        ];
    }

    /**
     * Build a checkout.session.completed event payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function buildCheckoutSessionCompletedEvent(string $sessionId, string $paymentIntentId, array $overrides = []): array
    {
        return array_merge([
            'id' => 'evt_test_'.fake()->regexify('[a-zA-Z0-9]{24}'),
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'payment_intent' => $paymentIntentId,
                    'payment_status' => 'paid',
                ],
            ],
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Signature verification (Rule 35)
    // -------------------------------------------------------------------------

    public function test_webhook_rejects_missing_signature(): void
    {
        $response = $this->postJson(route('webhooks.handle', ['provider' => 'stripe']), []);

        $response->assertStatus(400);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $response = $this->post(
            route('webhooks.handle', ['provider' => 'stripe']),
            [],
            [
                'Stripe-Signature' => 't=1234567890,v1=invalidsignature',
                'Content-Type' => 'application/json',
            ]
        );

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Happy path (Rule 29)
    // -------------------------------------------------------------------------

    public function test_valid_checkout_session_completed_webhook_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $book = Book::factory()->create(['price' => 59000]);
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
            'total_amount' => 59000,
        ]);
        OrderTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'provider_data' => ['session_id' => 'cs_test_valid_session'],
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'price' => 59000,
        ]);

        $event = $this->buildCheckoutSessionCompletedEvent('cs_test_valid_session', 'pi_test_intent_123');
        $webhook = $this->buildStripeWebhook($event);

        $response = $this->call(
            'POST',
            route('webhooks.handle', ['provider' => 'stripe']),
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $webhook['signature'], 'CONTENT_TYPE' => 'application/json'],
            $webhook['payload']
        );

        $response->assertStatus(200);

        Queue::assertPushed(ProcessPaymentConfirmation::class, function ($job) use ($order): bool {
            return $job->orderId === $order->id
                && $job->transactionId === 'pi_test_intent_123'
                && $job->sessionId === 'cs_test_valid_session'
                && $job->provider === PaymentGateway::Stripe;
        });
    }

    // -------------------------------------------------------------------------
    // Idempotency (Rule 30)
    // -------------------------------------------------------------------------

    public function test_webhook_skips_job_if_order_already_paid(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $order = Order::factory()->paid()->create([
            'user_id' => $user->id,
        ]);
        OrderTransaction::factory()->succeeded()->create([
            'order_id' => $order->id,
            'provider_data' => ['session_id' => 'cs_test_already_paid', 'transaction_id' => 'pi_test_intent_456'],
        ]);

        $event = $this->buildCheckoutSessionCompletedEvent('cs_test_already_paid', 'pi_test_intent_456');
        $webhook = $this->buildStripeWebhook($event);

        $response = $this->call(
            'POST',
            route('webhooks.handle', ['provider' => 'stripe']),
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $webhook['signature'], 'CONTENT_TYPE' => 'application/json'],
            $webhook['payload']
        );

        $response->assertStatus(200);

        // Controller short-circuits for already-paid orders — no job dispatched
        Queue::assertNotPushed(ProcessPaymentConfirmation::class);
    }

    public function test_webhook_skips_job_when_payment_status_is_not_paid(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'provider_data' => ['session_id' => 'cs_test_async_session'],
        ]);

        // Simulate an async payment method (e.g. bank transfer) where the session
        // completes but funds have not yet arrived — payment_status is 'unpaid'.
        $event = $this->buildCheckoutSessionCompletedEvent(
            'cs_test_async_session',
            'pi_test_async_intent',
            ['data' => ['object' => ['id' => 'cs_test_async_session', 'object' => 'checkout.session', 'payment_intent' => 'pi_test_async_intent', 'payment_status' => 'unpaid']]],
        );
        $webhook = $this->buildStripeWebhook($event);

        $response = $this->call(
            'POST',
            route('webhooks.handle', ['provider' => 'stripe']),
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $webhook['signature'], 'CONTENT_TYPE' => 'application/json'],
            $webhook['payload']
        );

        $response->assertStatus(200);
        Queue::assertNotPushed(ProcessPaymentConfirmation::class);
    }

    public function test_webhook_returns_200_when_order_not_found(): void
    {
        $event = $this->buildCheckoutSessionCompletedEvent('cs_test_unknown_session', 'pi_test_unknown');
        $webhook = $this->buildStripeWebhook($event);

        $response = $this->call(
            'POST',
            route('webhooks.handle', ['provider' => 'stripe']),
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $webhook['signature'], 'CONTENT_TYPE' => 'application/json'],
            $webhook['payload']
        );

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // ProcessPaymentConfirmation Job unit tests
    // -------------------------------------------------------------------------

    public function test_process_payment_confirmation_marks_order_paid_and_grants_user_books(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['price' => 59000]);
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
            'total_amount' => 59000,
        ]);
        OrderTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'provider_data' => ['session_id' => 'cs_test_job_test'],
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'price' => 59000,
        ]);

        $job = new ProcessPaymentConfirmation(
            $order->id,
            'pi_test_intent_job',
            'cs_test_job_test',
        );
        $job->handle();

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);

        // transaction_id is now stored in order_transactions, not on the order
        $this->assertDatabaseHas('order_transactions', [
            'order_id' => $order->id,
            'provider' => 'stripe',
            'status' => 'succeeded',
        ]);

        $this->assertDatabaseHas('user_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'order_id' => $order->id,
        ]);
    }

    public function test_process_payment_confirmation_is_idempotent_when_order_already_paid(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $order = Order::factory()->paid()->create([
            'user_id' => $user->id,
        ]);
        OrderTransaction::factory()->succeeded()->create([
            'order_id' => $order->id,
            'provider_data' => ['session_id' => 'cs_test_idempotent', 'transaction_id' => 'pi_original'],
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'book_id' => $book->id,
        ]);

        $originalPaidAt = $order->paid_at;

        $job = new ProcessPaymentConfirmation(
            $order->id,
            'pi_test_new_intent',
            'cs_test_idempotent',
        );
        $job->handle();

        // Order should remain paid with original timestamp, not overwritten
        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
        $this->assertEquals($originalPaidAt, $order->paid_at);
    }

    public function test_process_payment_confirmation_clears_user_cart(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
        ]);
        OrderTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'provider_data' => ['session_id' => 'cs_test_cart_clear'],
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'book_id' => $book->id,
        ]);

        // Add a cart item for the user (simulating leftover items)
        CartItem::factory()->create(['user_id' => $user->id]);

        $job = new ProcessPaymentConfirmation(
            $order->id,
            'pi_test_cart',
            'cs_test_cart_clear',
        );
        $job->handle();

        $this->assertDatabaseMissing('cart_items', ['user_id' => $user->id]);
    }

    // -------------------------------------------------------------------------
    // checkout.session.expired event
    // -------------------------------------------------------------------------

    public function test_webhook_handles_session_expired_event(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);
        $transaction = OrderTransaction::factory()->pending()->create([
            'order_id' => $order->id,
            'provider_data' => ['session_id' => 'cs_test_expired_session'],
        ]);

        $event = [
            'id' => 'evt_test_'.fake()->regexify('[a-zA-Z0-9]{24}'),
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => 'cs_test_expired_session',
                    'object' => 'checkout.session',
                ],
            ],
        ];
        $webhook = $this->buildStripeWebhook($event);

        $response = $this->call(
            'POST',
            route('webhooks.handle', ['provider' => 'stripe']),
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $webhook['signature'], 'CONTENT_TYPE' => 'application/json'],
            $webhook['payload']
        );

        $response->assertStatus(200);

        $transaction->refresh();
        $this->assertSame('expired', $transaction->status);

        $order->refresh();
        $this->assertEquals(OrderStatus::Failed, $order->status);
    }

    public function test_webhook_ignores_expired_event_when_transaction_not_found(): void
    {
        $event = [
            'id' => 'evt_test_'.fake()->regexify('[a-zA-Z0-9]{24}'),
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => 'cs_test_unknown_session',
                    'object' => 'checkout.session',
                ],
            ],
        ];
        $webhook = $this->buildStripeWebhook($event);

        $response = $this->call(
            'POST',
            route('webhooks.handle', ['provider' => 'stripe']),
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $webhook['signature'], 'CONTENT_TYPE' => 'application/json'],
            $webhook['payload']
        );

        $response->assertStatus(200);
    }

    public function test_webhook_does_not_double_expire_already_succeeded_transaction(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->paid()->create(['user_id' => $user->id]);
        $transaction = OrderTransaction::factory()->succeeded()->create([
            'order_id' => $order->id,
            'provider_data' => ['session_id' => 'cs_test_paid_session', 'transaction_id' => 'pi_test_x'],
        ]);

        $event = [
            'id' => 'evt_test_'.fake()->regexify('[a-zA-Z0-9]{24}'),
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => 'cs_test_paid_session',
                    'object' => 'checkout.session',
                ],
            ],
        ];
        $webhook = $this->buildStripeWebhook($event);

        $this->call(
            'POST',
            route('webhooks.handle', ['provider' => 'stripe']),
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $webhook['signature'], 'CONTENT_TYPE' => 'application/json'],
            $webhook['payload']
        );

        // Transaction must remain succeeded, order must remain paid
        $transaction->refresh();
        $this->assertSame('succeeded', $transaction->status);

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
    }
}
