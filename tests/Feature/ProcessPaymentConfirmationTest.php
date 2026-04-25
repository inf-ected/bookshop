<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Features\Checkout\Jobs\ProcessPaymentConfirmation;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTransaction;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessPaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $user, Book $book): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'price' => $book->price,
        ]);

        OrderTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_data' => ['session_id' => 'cs_test_123'],
        ]);

        return $order;
    }

    public function test_grants_user_book_on_first_purchase(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $order = $this->makeOrder($user, $book);

        (new ProcessPaymentConfirmation($order->id, 'pi_test_123', 'cs_test_123'))->handle();

        $this->assertDatabaseHas('user_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'revoked_at' => null,
        ]);
    }

    public function test_restores_revoked_user_book_on_repurchase(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $order = $this->makeOrder($user, $book);

        $revokedUserBook = UserBook::factory()->revoked()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->assertNotNull($revokedUserBook->revoked_at);

        (new ProcessPaymentConfirmation($order->id, 'pi_test_123', 'cs_test_123'))->handle();

        $this->assertNull($revokedUserBook->fresh()->revoked_at);
        $this->assertSame($order->id, $revokedUserBook->fresh()->order_id);
        $this->assertDatabaseCount('user_books', 1);
    }

    public function test_marks_order_paid_and_transaction_succeeded(): void
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

        (new ProcessPaymentConfirmation($order->id, 'pi_test_intent_job', 'cs_test_job_test'))->handle();

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);

        $this->assertDatabaseHas('order_transactions', [
            'order_id' => $order->id,
            'status' => 'succeeded',
        ]);

        $this->assertDatabaseHas('user_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'order_id' => $order->id,
        ]);
    }

    public function test_idempotent_when_order_already_paid(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'book_id' => $book->id,
        ]);

        $originalPaidAt = $order->paid_at;

        (new ProcessPaymentConfirmation($order->id, 'pi_test_123', 'cs_test_123'))->handle();

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
        $this->assertEquals($originalPaidAt, $order->paid_at);

        $this->assertDatabaseMissing('user_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_clears_user_cart_after_payment(): void
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

        CartItem::factory()->create(['user_id' => $user->id]);

        (new ProcessPaymentConfirmation($order->id, 'pi_test_cart', 'cs_test_cart_clear'))->handle();

        $this->assertDatabaseMissing('cart_items', ['user_id' => $user->id]);
    }
}
