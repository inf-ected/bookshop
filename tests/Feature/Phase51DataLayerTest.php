<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTransaction;
use App\Models\User;
use App\Models\UserBook;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase51DataLayerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Migrations / schema
    // -------------------------------------------------------------------------

    public function test_cart_items_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(\Schema::hasTable('cart_items'));
        $this->assertTrue(\Schema::hasColumns('cart_items', [
            'id', 'user_id', 'session_id', 'book_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_orders_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(\Schema::hasTable('orders'));
        $this->assertTrue(\Schema::hasColumns('orders', [
            'id', 'user_id', 'status', 'total_amount', 'currency',
            'paid_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_order_transactions_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(\Schema::hasTable('order_transactions'));
        $this->assertTrue(\Schema::hasColumns('order_transactions', [
            'id', 'order_id', 'provider', 'provider_data', 'status',
            'expires_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_order_items_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(\Schema::hasTable('order_items'));
        $this->assertTrue(\Schema::hasColumns('order_items', [
            'id', 'order_id', 'book_id', 'price', 'currency', 'created_at', 'updated_at',
        ]));
    }

    public function test_user_books_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(\Schema::hasTable('user_books'));
        $this->assertTrue(\Schema::hasColumns('user_books', [
            'id', 'user_id', 'book_id', 'order_id', 'granted_at', 'created_at', 'updated_at',
        ]));
    }

    // -------------------------------------------------------------------------
    // OrderStatus enum
    // -------------------------------------------------------------------------

    public function test_order_status_enum_has_correct_cases(): void
    {
        $this->assertSame('pending', OrderStatus::Pending->value);
        $this->assertSame('paid', OrderStatus::Paid->value);
        $this->assertSame('refunded', OrderStatus::Refunded->value);
        $this->assertSame('failed', OrderStatus::Failed->value);
    }

    // -------------------------------------------------------------------------
    // CartItem model
    // -------------------------------------------------------------------------

    public function test_cart_item_factory_creates_valid_record(): void
    {
        $cartItem = CartItem::factory()->create();

        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id]);
        $this->assertNotNull($cartItem->book_id);
    }

    public function test_cart_item_factory_guest_state_creates_session_cart(): void
    {
        $cartItem = CartItem::factory()->forGuest()->create();

        $this->assertNull($cartItem->user_id);
        $this->assertNotNull($cartItem->session_id);
    }

    public function test_cart_item_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $cartItem = CartItem::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $cartItem->user);
        $this->assertSame($user->id, $cartItem->user->id);
    }

    public function test_cart_item_belongs_to_book(): void
    {
        $book = Book::factory()->create();
        $cartItem = CartItem::factory()->create(['book_id' => $book->id]);

        $this->assertInstanceOf(Book::class, $cartItem->book);
        $this->assertSame($book->id, $cartItem->book->id);
    }

    public function test_cart_items_user_book_unique_constraint_prevents_duplicates(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book->id, 'session_id' => null]);

        $this->expectException(QueryException::class);
        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book->id, 'session_id' => null]);
    }

    // -------------------------------------------------------------------------
    // Order model
    // -------------------------------------------------------------------------

    public function test_order_factory_creates_valid_record(): void
    {
        $order = Order::factory()->create();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertInstanceOf(OrderStatus::class, $order->status);
    }

    public function test_order_status_cast_to_enum(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_order_paid_at_cast_to_datetime(): void
    {
        $order = Order::factory()->paid()->create();

        $this->assertInstanceOf(Carbon::class, $order->paid_at);
    }

    public function test_order_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $order->user);
        $this->assertSame($user->id, $order->user->id);
    }

    public function test_order_has_many_items(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->count(3)->create(['order_id' => $order->id]);

        $this->assertCount(3, $order->items);
    }

    public function test_order_has_many_user_books(): void
    {
        $order = Order::factory()->create();
        UserBook::factory()->count(2)->create(['order_id' => $order->id]);

        $this->assertCount(2, $order->userBooks);
    }

    public function test_order_factory_states(): void
    {
        $pending = Order::factory()->pending()->create();
        $paid = Order::factory()->paid()->create();
        $failed = Order::factory()->failed()->create();
        $refunded = Order::factory()->refunded()->create();

        $this->assertSame(OrderStatus::Pending, $pending->status);
        $this->assertSame(OrderStatus::Paid, $paid->status);
        $this->assertSame(OrderStatus::Failed, $failed->status);
        $this->assertSame(OrderStatus::Refunded, $refunded->status);
    }

    public function test_order_transaction_factory_creates_valid_record(): void
    {
        $transaction = OrderTransaction::factory()->create();

        $this->assertDatabaseHas('order_transactions', ['id' => $transaction->id]);
        $this->assertSame('stripe', $transaction->provider);
        $this->assertIsArray($transaction->provider_data);
    }

    public function test_order_transaction_factory_states(): void
    {
        $order = Order::factory()->create();

        $pending = OrderTransaction::factory()->pending()->create(['order_id' => $order->id]);
        $succeeded = OrderTransaction::factory()->succeeded()->create(['order_id' => $order->id]);
        $failed = OrderTransaction::factory()->failed()->create(['order_id' => $order->id]);
        $expired = OrderTransaction::factory()->expired()->create(['order_id' => $order->id]);

        $this->assertSame('pending', $pending->status);
        $this->assertSame('succeeded', $succeeded->status);
        $this->assertSame('failed', $failed->status);
        $this->assertSame('expired', $expired->status);
    }

    public function test_order_transaction_belongs_to_order(): void
    {
        $order = Order::factory()->create();
        $transaction = OrderTransaction::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(Order::class, $transaction->order);
        $this->assertSame($order->id, $transaction->order->id);
    }

    public function test_order_has_many_transactions(): void
    {
        $order = Order::factory()->create();
        OrderTransaction::factory()->count(2)->create(['order_id' => $order->id]);

        $this->assertCount(2, $order->transactions);
    }

    public function test_order_transaction_provider_data_cast_to_array(): void
    {
        $transaction = OrderTransaction::factory()->create([
            'provider_data' => ['session_id' => 'cs_test_abc', 'payment_intent' => 'pi_test_xyz'],
        ]);

        $fresh = $transaction->fresh();
        $this->assertIsArray($fresh->provider_data);
        $this->assertSame('cs_test_abc', $fresh->provider_data['session_id']);
    }

    // -------------------------------------------------------------------------
    // OrderItem model
    // -------------------------------------------------------------------------

    public function test_order_item_factory_creates_valid_record(): void
    {
        $orderItem = OrderItem::factory()->create();

        $this->assertDatabaseHas('order_items', ['id' => $orderItem->id]);
        $this->assertIsInt($orderItem->price);
    }

    public function test_order_item_belongs_to_order(): void
    {
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(Order::class, $orderItem->order);
        $this->assertSame($order->id, $orderItem->order->id);
    }

    public function test_order_item_belongs_to_book(): void
    {
        $book = Book::factory()->create();
        $orderItem = OrderItem::factory()->create(['book_id' => $book->id]);

        $this->assertInstanceOf(Book::class, $orderItem->book);
        $this->assertSame($book->id, $orderItem->book->id);
    }

    // -------------------------------------------------------------------------
    // UserBook model
    // -------------------------------------------------------------------------

    public function test_user_book_factory_creates_valid_record(): void
    {
        $userBook = UserBook::factory()->create();

        $this->assertDatabaseHas('user_books', ['id' => $userBook->id]);
    }

    public function test_user_book_granted_at_cast_to_datetime(): void
    {
        $userBook = UserBook::factory()->create();

        $this->assertInstanceOf(Carbon::class, $userBook->granted_at);
    }

    public function test_user_book_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $userBook = UserBook::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $userBook->user);
        $this->assertSame($user->id, $userBook->user->id);
    }

    public function test_user_book_belongs_to_book(): void
    {
        $book = Book::factory()->create();
        $userBook = UserBook::factory()->create(['book_id' => $book->id]);

        $this->assertInstanceOf(Book::class, $userBook->book);
        $this->assertSame($book->id, $userBook->book->id);
    }

    public function test_user_book_belongs_to_order(): void
    {
        $order = Order::factory()->create();
        $userBook = UserBook::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(Order::class, $userBook->order);
        $this->assertSame($order->id, $userBook->order->id);
    }

    public function test_user_book_order_can_be_null(): void
    {
        $userBook = UserBook::factory()->create(['order_id' => null]);

        $this->assertNull($userBook->order_id);
        $this->assertNull($userBook->order);
    }

    public function test_user_books_unique_constraint_prevents_duplicate_ownership(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->expectException(QueryException::class);
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);
    }
}
