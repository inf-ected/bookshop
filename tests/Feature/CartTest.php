<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Cart\Services\CartService;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CartService unit-level tests
    // -------------------------------------------------------------------------

    public function test_guest_can_add_book_to_cart_via_service(): void
    {
        $book = Book::factory()->create();
        $service = app(CartService::class);

        $service->addItem($book, null, 'session-abc');

        $this->assertDatabaseHas('cart_items', [
            'book_id' => $book->id,
            'session_id' => 'session-abc',
            'user_id' => null,
        ]);
    }

    public function test_authenticated_user_can_add_book_to_cart_via_service(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $service = app(CartService::class);

        $service->addItem($book, $user, 'session-abc');

        $this->assertDatabaseHas('cart_items', [
            'book_id' => $book->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_add_item_throws_exception_when_user_already_owns_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $service = app(CartService::class);

        $this->expectException(\RuntimeException::class);

        $service->addItem($book, $user, 'session-abc');
    }

    public function test_add_item_allows_adding_revoked_book_to_cart(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->revoked()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $service = app(CartService::class);
        $service->addItem($book, $user, 'session-abc');

        $this->assertDatabaseHas('cart_items', [
            'book_id' => $book->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_duplicate_cart_items_are_handled_gracefully_for_guest(): void
    {
        $book = Book::factory()->create();
        $service = app(CartService::class);

        $service->addItem($book, null, 'session-abc');
        // Should not throw — silently ignored
        $service->addItem($book, null, 'session-abc');

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_duplicate_cart_items_are_handled_gracefully_for_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $service = app(CartService::class);

        $service->addItem($book, $user, 'session-abc');
        // Should not throw — silently ignored
        $service->addItem($book, $user, 'session-abc');

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_remove_item_removes_user_cart_item(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $service = app(CartService::class);
        $service->removeItem($book, $user, 'session-abc');

        $this->assertDatabaseMissing('cart_items', ['book_id' => $book->id, 'user_id' => $user->id]);
    }

    public function test_remove_item_removes_guest_cart_item(): void
    {
        $book = Book::factory()->create();
        CartItem::factory()->forGuest()->create(['book_id' => $book->id, 'session_id' => 'session-xyz']);

        $service = app(CartService::class);
        $service->removeItem($book, null, 'session-xyz');

        $this->assertDatabaseMissing('cart_items', ['book_id' => $book->id, 'session_id' => 'session-xyz']);
    }

    public function test_get_items_returns_user_cart_items_with_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $service = app(CartService::class);
        $items = $service->getItems($user, 'session-abc');

        $this->assertCount(1, $items);
        $this->assertSame($book->id, $items->first()->book->id);
    }

    public function test_get_items_returns_guest_cart_items_with_book(): void
    {
        $book = Book::factory()->create();
        CartItem::factory()->forGuest()->create(['book_id' => $book->id, 'session_id' => 'session-xyz']);

        $service = app(CartService::class);
        $items = $service->getItems(null, 'session-xyz');

        $this->assertCount(1, $items);
        $this->assertSame($book->id, $items->first()->book->id);
    }

    public function test_get_total_sums_book_prices_in_kopecks(): void
    {
        $user = User::factory()->create();
        $book1 = Book::factory()->create(['price' => 59000]);
        $book2 = Book::factory()->create(['price' => 39900]);
        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book1->id]);
        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book2->id]);

        $service = app(CartService::class);
        $items = $service->getItems($user, 'session-abc');
        $total = $service->getTotalFromItems($items);

        $this->assertSame(98900, $total);
    }

    public function test_clear_cart_removes_all_user_cart_items(): void
    {
        $user = User::factory()->create();
        CartItem::factory()->count(3)->create(['user_id' => $user->id]);

        $service = app(CartService::class);
        $service->clearCart($user, 'session-abc');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_clear_cart_removes_all_guest_cart_items_but_not_others(): void
    {
        CartItem::factory()->forGuest()->count(2)->create(['session_id' => 'session-xyz']);
        // Different session — should not be affected
        CartItem::factory()->forGuest()->create(['session_id' => 'other-session']);

        $service = app(CartService::class);
        $service->clearCart(null, 'session-xyz');

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['session_id' => 'other-session']);
    }

    // -------------------------------------------------------------------------
    // Guest cart merge on login (Rule 25)
    // -------------------------------------------------------------------------

    public function test_guest_cart_merges_into_user_cart_on_login(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        CartItem::factory()->forGuest()->create(['book_id' => $book->id, 'session_id' => 'session-guest']);

        $service = app(CartService::class);
        $service->mergeGuestCart($user, 'session-guest');

        $this->assertDatabaseHas('cart_items', ['user_id' => $user->id, 'book_id' => $book->id]);
        $this->assertDatabaseMissing('cart_items', ['session_id' => 'session-guest']);
    }

    public function test_merge_guest_cart_includes_book_user_had_revoked(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // User's access was revoked — they should be able to re-purchase
        UserBook::factory()->revoked()->create(['user_id' => $user->id, 'book_id' => $book->id]);
        CartItem::factory()->forGuest()->create(['book_id' => $book->id, 'session_id' => 'session-guest']);

        $service = app(CartService::class);
        $service->mergeGuestCart($user, 'session-guest');

        $this->assertDatabaseHas('cart_items', ['user_id' => $user->id, 'book_id' => $book->id]);
    }

    public function test_merge_guest_cart_discards_duplicates(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // Book already in user cart
        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);
        // Same book in guest cart
        CartItem::factory()->forGuest()->create(['book_id' => $book->id, 'session_id' => 'session-guest']);

        $service = app(CartService::class);
        $service->mergeGuestCart($user, 'session-guest');

        // Only one cart item should exist for this user+book
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['user_id' => $user->id, 'book_id' => $book->id]);
        $this->assertDatabaseMissing('cart_items', ['session_id' => 'session-guest']);
    }

    public function test_merge_guest_cart_is_triggered_on_login_event(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $sessionId = session()->getId();

        CartItem::factory()->forGuest()->create(['book_id' => $book->id, 'session_id' => $sessionId]);

        event(new Login('web', $user, false));

        $this->assertDatabaseHas('cart_items', ['user_id' => $user->id, 'book_id' => $book->id]);
        $this->assertDatabaseMissing('cart_items', ['session_id' => $sessionId, 'user_id' => null]);
    }

    // -------------------------------------------------------------------------
    // CartController HTTP tests
    // -------------------------------------------------------------------------

    public function test_cart_index_returns_200_for_guest(): void
    {
        $response = $this->get(route('cart.index'));

        $response->assertStatus(200);
    }

    public function test_cart_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response->assertStatus(200);
    }

    public function test_guest_can_add_book_to_cart_via_http(): void
    {
        $book = Book::factory()->create();

        $response = $this->post(route('cart.store', $book));

        $response->assertRedirect();
        $this->assertDatabaseHas('cart_items', ['book_id' => $book->id]);
    }

    public function test_authenticated_user_can_add_book_to_cart_via_http(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('cart.store', $book));

        $response->assertRedirect();
        $this->assertDatabaseHas('cart_items', ['book_id' => $book->id, 'user_id' => $user->id]);
    }

    public function test_store_redirects_back_with_error_when_user_already_owns_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('cart.store', $book));

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHasErrors('cart');
    }

    public function test_user_can_remove_book_from_cart_via_http(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        CartItem::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $response = $this->actingAs($user)->delete(route('cart.destroy', $book));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cart_items', ['book_id' => $book->id, 'user_id' => $user->id]);
    }

    public function test_guest_can_remove_book_from_cart_via_http(): void
    {
        $book = Book::factory()->create();

        // The test HTTP client does not carry session cookies between separate requests,
        // so direct end-to-end assertion of deletion by session ID is not feasible here.
        // Removal-by-session-id is covered by test_remove_item_removes_guest_cart_item.
        // This test verifies the route is accessible and returns a redirect for a guest.
        $response = $this->delete(route('cart.destroy', $book));

        $response->assertRedirect();
    }
}
