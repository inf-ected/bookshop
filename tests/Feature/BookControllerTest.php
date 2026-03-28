<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
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
}
