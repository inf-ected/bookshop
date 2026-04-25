<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Features\Checkout\Exceptions\PaymentException;
use App\Features\Checkout\Services\PayPalPaymentProvider;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $this->assertSame(PaymentGateway::PayPal, $this->provider->getName());
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

        $this->provider->handleReturn($request, $order);

        Http::assertNothingSent();
    }
}
