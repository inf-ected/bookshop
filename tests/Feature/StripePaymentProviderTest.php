<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Features\Checkout\Exceptions\PaymentException;
use App\Features\Checkout\Services\StripePaymentProvider;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class StripePaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    private StripePaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.webhook_secret' => 'whsec_test_fake',
        ]);

        $this->provider = $this->app->make(StripePaymentProvider::class);
    }

    // -------------------------------------------------------------------------
    // getName()
    // -------------------------------------------------------------------------

    public function test_get_name_returns_stripe(): void
    {
        $this->assertSame(PaymentGateway::Stripe, $this->provider->getName());
    }

    // -------------------------------------------------------------------------
    // extractReturnSessionId()
    // -------------------------------------------------------------------------

    public function test_extract_return_session_id_reads_session_id_query_param(): void
    {
        $request = Request::create('/checkout/success?session_id=cs_test_abc123');

        $this->assertSame('cs_test_abc123', $this->provider->extractReturnSessionId($request));
    }

    public function test_extract_return_session_id_returns_null_when_missing(): void
    {
        $request = Request::create('/checkout/success');

        $this->assertNull($this->provider->extractReturnSessionId($request));
    }

    // -------------------------------------------------------------------------
    // handleReturn()
    // -------------------------------------------------------------------------

    public function test_handle_return_is_noop(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);
        $request = Request::create('/checkout/success?session_id=cs_test_abc123');

        // Should not throw — Stripe confirms payment via webhook, not on return
        $this->provider->handleReturn($request, $order);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // createSession()
    // -------------------------------------------------------------------------

    public function test_create_session_returns_id_and_url_and_creates_transaction(): void
    {
        $sessionId = 'cs_test_abc123';
        $sessionUrl = 'https://checkout.stripe.com/pay/'.$sessionId;

        ApiRequestor::setHttpClient($this->makeStripeHttpMock(200, [
            'id' => $sessionId,
            'object' => 'checkout.session',
            'url' => $sessionUrl,
            'payment_intent' => 'pi_test_intent_abc',
            'payment_status' => 'unpaid',
            'expires_at' => now()->addMinutes(30)->timestamp,
            'livemode' => false,
        ]));

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

        $this->assertSame($sessionId, $result['id']);
        $this->assertSame($sessionUrl, $result['url']);

        $this->assertDatabaseHas('order_transactions', [
            'order_id' => $order->id,
            'provider' => 'stripe',
            'status' => 'pending',
        ]);
    }

    public function test_create_session_throws_on_api_failure(): void
    {
        ApiRequestor::setHttpClient($this->makeStripeHttpMock(500, [
            'error' => ['type' => 'api_error', 'message' => 'Internal server error'],
        ]));

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
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a fake Stripe HTTP client that returns the given status + body.
     *
     * @param  array<string, mixed>  $body
     */
    private function makeStripeHttpMock(int $status, array $body): ClientInterface
    {
        return new class($status, $body) implements ClientInterface
        {
            /** @param array<string, mixed> $body */
            public function __construct(
                private readonly int $status,
                private readonly array $body,
            ) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
            {
                return [json_encode($this->body), $this->status, []];
            }
        };
    }
}
