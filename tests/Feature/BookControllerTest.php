<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookFile;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_page_returns_200(): void
    {
        $response = $this->get('/books');

        $response->assertStatus(200);
    }

    public function test_book_detail_returns_200_for_published_book(): void
    {
        $book = Book::factory()->published()->create();

        $response = $this->get("/books/{$book->slug}");

        $response->assertStatus(200);
    }

    public function test_book_detail_returns_404_for_draft_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->get("/books/{$book->slug}");

        $response->assertStatus(404);
    }

    public function test_fragment_page_returns_200_for_published_book(): void
    {
        $book = Book::factory()->published()->create();

        $response = $this->get("/books/{$book->slug}/fragment");

        $response->assertStatus(200);
    }

    public function test_fragment_page_returns_404_for_draft_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->get("/books/{$book->slug}/fragment");

        $response->assertStatus(404);
    }

    public function test_book_detail_shows_download_buttons_for_owner_with_ready_files(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->published()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);
        BookFile::factory()->epub()->ready()->create(['book_id' => $book->id]);

        $response = $this->actingAs($user)->get(route('books.show', $book));

        $response->assertOk();
        $response->assertViewHas('readyClientFiles');
        $this->assertCount(1, $response->viewData('readyClientFiles'));
        $response->assertSee(route('books.download', [$book, 'format' => 'epub']), false);
    }

    public function test_book_detail_shows_library_fallback_for_owner_with_no_ready_files(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->published()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $response = $this->actingAs($user)->get(route('books.show', $book));

        $response->assertOk();
        $response->assertViewHas('readyClientFiles');
        $this->assertCount(0, $response->viewData('readyClientFiles'));
        $response->assertSee(route('cabinet.library'), false);
    }

    public function test_book_detail_passes_empty_ready_files_for_non_owner(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->published()->create();
        BookFile::factory()->epub()->ready()->create(['book_id' => $book->id]);

        $response = $this->actingAs($user)->get(route('books.show', $book));

        $response->assertOk();
        $response->assertViewHas('readyClientFiles');
        $this->assertCount(0, $response->viewData('readyClientFiles'));
    }
}
