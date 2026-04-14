<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Features\Checkout\Jobs\ProcessPaymentConfirmation;
use App\Models\Book;
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

        // Simulate a previously revoked entry
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

        (new ProcessPaymentConfirmation($order->id, 'pi_test_123', 'cs_test_123'))->handle();

        // No user_book should be created because the order was already paid
        $this->assertDatabaseMissing('user_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }
}
