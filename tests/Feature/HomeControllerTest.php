<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_returns_200(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_homepage_passes_featured_books_to_view(): void
    {
        Book::factory()->published()->featured()->count(2)->create();
        Book::factory()->published()->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('books', function ($books) {
            return $books->count() === 2;
        });
    }
}
