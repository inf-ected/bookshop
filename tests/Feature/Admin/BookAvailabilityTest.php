<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_toggle_availability_on(): void
    {
        $book = Book::factory()->published()->unavailable()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.books.toggle-availability', $book));

        $response->assertOk();
        $response->assertJson(['is_available' => true]);
        $this->assertTrue($book->fresh()->is_available);
    }

    public function test_admin_can_toggle_availability_off(): void
    {
        $book = Book::factory()->published()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.books.toggle-availability', $book));

        $response->assertOk();
        $response->assertJson(['is_available' => false]);
        $this->assertFalse($book->fresh()->is_available);
    }

    public function test_unavailable_book_excluded_from_catalog_scope(): void
    {
        Book::factory()->published()->unavailable()->create();

        $this->assertCount(0, Book::query()->published()->get());
    }

    public function test_available_published_book_in_catalog_scope(): void
    {
        Book::factory()->published()->create(['is_available' => true]);

        $this->assertCount(1, Book::query()->published()->get());
    }

    public function test_unavailable_book_still_accessible_by_direct_url(): void
    {
        $book = Book::factory()->published()->unavailable()->create();

        $response = $this->get(route('books.show', $book));

        $response->assertOk();
    }

    public function test_guest_cannot_toggle_availability(): void
    {
        $book = Book::factory()->published()->create();

        $response = $this->patch(route('admin.books.toggle-availability', $book));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_toggle_availability(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $book = Book::factory()->published()->create();

        $response = $this->actingAs($user)
            ->patch(route('admin.books.toggle-availability', $book));

        $response->assertNotFound();
    }
}
