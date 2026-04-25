<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookFile;
use App\Models\Order;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CabinetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_cabinet_index_redirects_to_library(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cabinet.index'));

        $response->assertRedirectToRoute('cabinet.library');
    }

    public function test_unauthenticated_user_redirected_from_cabinet(): void
    {
        $response = $this->get(route('cabinet.index'));

        $response->assertRedirectToRoute('login');
    }

    public function test_library_shows_owned_books(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $response = $this->actingAs($user)->get(route('cabinet.library'));

        $response->assertOk();
        $response->assertViewHas('userBooks');

        $userBooks = $response->viewData('userBooks');
        $this->assertTrue($userBooks->contains('book_id', $book->id));
    }

    public function test_library_shows_download_button_for_ready_epub(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);
        BookFile::factory()->epub()->ready()->create(['book_id' => $book->id]);

        $response = $this->actingAs($user)->get(route('cabinet.library'));

        $response->assertOk();
        $response->assertSee(route('books.download', [$book, 'format' => 'epub']), false);
    }

    public function test_library_shows_download_button_for_ready_fb2(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);
        BookFile::factory()->fb2()->ready()->create(['book_id' => $book->id]);

        $response = $this->actingAs($user)->get(route('cabinet.library'));

        $response->assertOk();
        $response->assertSee(route('books.download', [$book, 'format' => 'fb2']), false);
    }

    public function test_library_shows_both_format_buttons_when_epub_and_fb2_ready(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);
        BookFile::factory()->epub()->ready()->create(['book_id' => $book->id]);
        BookFile::factory()->fb2()->ready()->create(['book_id' => $book->id]);

        $response = $this->actingAs($user)->get(route('cabinet.library'));

        $response->assertOk();
        $response->assertSee(route('books.download', [$book, 'format' => 'epub']), false);
        $response->assertSee(route('books.download', [$book, 'format' => 'fb2']), false);
    }

    public function test_library_shows_preparing_message_when_no_ready_files(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $response = $this->actingAs($user)->get(route('cabinet.library'));

        $response->assertOk();
        $response->assertSee('Файл готовится');
    }

    public function test_library_shows_empty_when_no_books_owned(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cabinet.library'));

        $response->assertOk();
    }

    public function test_orders_page_shows_paginated_orders(): void
    {
        $user = User::factory()->create();

        // Create 12 orders — should paginate at 10
        Order::factory()->count(12)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('cabinet.orders'));

        $response->assertOk();
        $response->assertViewHas('orders');

        $orders = $response->viewData('orders');
        $this->assertEquals(10, $orders->count());
        $this->assertTrue($orders->hasMorePages());
    }

    public function test_orders_page_is_empty_when_no_orders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cabinet.orders'));

        $response->assertOk();
    }

    public function test_unverified_user_redirected_from_cabinet(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('cabinet.library'));

        $response->assertRedirectToRoute('verification.notice');
    }
}
