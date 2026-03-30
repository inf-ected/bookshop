<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PaymentProvider;
use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Exception\InvalidRequestException;
use Tests\TestCase;

class CheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @var array{id: string, url: string} */
    private array $fakeSession = [
        'id' => 'cs_test_fake_session_id',
        'url' => 'https://checkout.stripe.com/pay/cs_test_fake_session_id',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a fake PaymentProvider so tests never hit real Stripe API
        $this->app->bind(PaymentProvider::class, function () {
            return new class($this->fakeSession) implements PaymentProvider
            {
                /** @param array{id: string, url: string} $session */
                public function __construct(private readonly array $session) {}

                public function createSession(Order $order, User $user): array
                {
                    return $this->session;
                }
            };
        });
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post(route('checkout.store'));

        $response->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post(route('checkout.store'));

        $response->assertRedirect(route('verification.notice'));
    }

    // -------------------------------------------------------------------------
    // Empty cart
    // -------------------------------------------------------------------------

    public function test_empty_cart_redirects_to_cart_with_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('checkout.store'));

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHasErrors('cart');

        $errorMessage = session('errors')->first('cart');
        $this->assertSame('Корзина пуста.', $errorMessage);
    }

    // -------------------------------------------------------------------------
    // Happy path (Rule 27 + 28)
    // -------------------------------------------------------------------------

    public function test_happy_path_creates_order_with_pending_status_and_redirects_to_stripe(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['price' => 59000]);

        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $response = $this->actingAs($user)->post(route('checkout.store'));

        // Rule 27: order created before redirect
        $this->assertDatabaseCount('orders', 1);

        $order = Order::query()->first();
        $this->assertEquals(OrderStatus::Pending, $order->status);
        $this->assertSame('cs_test_fake_session_id', $order->stripe_session_id);

        // Rule 28: price snapshot stored on order_item
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'book_id' => $book->id,
            'price' => 59000,
            'currency' => 'RUB',
        ]);

        // Cart is NOT cleared here — it is cleared by ProcessPaymentConfirmation
        // after the webhook confirms payment (prevents cart loss on Stripe failure)
        $this->assertDatabaseCount('cart_items', 1);

        // Redirected to Stripe URL
        $response->assertRedirect($this->fakeSession['url']);
    }

    public function test_order_items_capture_price_snapshot_not_current_book_price(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['price' => 49900]);

        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->actingAs($user)->post(route('checkout.store'));

        $order = Order::query()->first();

        // Price at the time of purchase, not necessarily the current price
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'price' => 49900,
        ]);
    }

    public function test_total_amount_on_order_is_sum_of_item_prices(): void
    {
        $user = User::factory()->create();
        $book1 = Book::factory()->create(['price' => 59000]);
        $book2 = Book::factory()->create(['price' => 39900]);

        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book1->id]);
        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book2->id]);

        $this->actingAs($user)->post(route('checkout.store'));

        $order = Order::query()->first();

        $this->assertSame(98900, $order->total_amount);
    }

    // -------------------------------------------------------------------------
    // Stripe API failure
    // -------------------------------------------------------------------------

    public function test_stripe_api_failure_marks_order_failed_and_redirects_to_cart(): void
    {
        // Override the fake with one that throws
        $this->app->bind(PaymentProvider::class, function () {
            return new class implements PaymentProvider
            {
                public function createSession(Order $order, User $user): array
                {
                    throw new InvalidRequestException('No such customer');
                }
            };
        });

        $user = User::factory()->create();
        $book = Book::factory()->create(['price' => 59000]);

        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $response = $this->actingAs($user)->post(route('checkout.store'));

        // Order should exist but be marked as failed
        $this->assertDatabaseCount('orders', 1);

        $order = Order::query()->first();
        $this->assertEquals(OrderStatus::Failed, $order->status);

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHasErrors('cart');

        $errorMessage = session('errors')->first('cart');
        $this->assertSame('Ошибка при создании платежа. Попробуйте позже.', $errorMessage);
    }

    // -------------------------------------------------------------------------
    // Success page (Rule 33)
    // -------------------------------------------------------------------------

    public function test_success_page_is_accessible_to_verified_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('checkout.success'));

        $response->assertOk();
        $response->assertViewIs('checkout.success');
    }

    public function test_success_page_redirects_guest_to_login(): void
    {
        $response = $this->get(route('checkout.success'));

        $response->assertRedirect(route('login'));
    }

    public function test_success_page_redirects_to_library_when_order_already_paid(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->paid()->create([
            'user_id' => $user->id,
            'stripe_session_id' => 'cs_test_already_paid',
        ]);

        $response = $this->actingAs($user)
            ->get(route('checkout.success').'?session_id=cs_test_already_paid');

        $response->assertRedirect(route('cabinet.index'));
    }

    public function test_success_page_shows_polling_view_when_order_still_pending(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
            'stripe_session_id' => 'cs_test_pending',
        ]);

        $response = $this->actingAs($user)
            ->get(route('checkout.success').'?session_id=cs_test_pending');

        $response->assertOk();
        $response->assertViewIs('checkout.success');
    }

    // -------------------------------------------------------------------------
    // Status endpoint (Rule 33)
    // -------------------------------------------------------------------------

    public function test_status_endpoint_returns_order_status_json(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->paid()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson(route('checkout.status', $order));

        $response->assertOk();
        $response->assertJson(['status' => 'paid', 'paid' => true]);
    }

    public function test_status_endpoint_returns_pending_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson(route('checkout.status', $order));

        $response->assertOk();
        $response->assertJson(['status' => 'pending', 'paid' => false]);
    }

    public function test_status_endpoint_returns_404_for_other_users_order(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)
            ->getJson(route('checkout.status', $order));

        $response->assertNotFound();
    }

    public function test_status_endpoint_requires_auth(): void
    {
        $order = Order::factory()->pending()->create();

        $response = $this->getJson(route('checkout.status', $order));

        $response->assertUnauthorized();
    }
}
