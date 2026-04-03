<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BookStatus;
use App\Jobs\ProcessBookFileUpload;
use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBookControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        // Guests hit the 'auth' middleware first, which redirects to login.
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertStatus(404);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertStatus(200);
    }

    public function test_admin_can_access_books_index(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/books')->assertStatus(200);
    }

    public function test_admin_can_access_create_book_page(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/books/create')->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Create book
    // -------------------------------------------------------------------------

    public function test_admin_can_create_a_book(): void
    {
        Storage::fake('s3-public');

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/books', [
            'title' => 'Тестовая книга',
            'slug' => 'test-book',
            'price' => '590',
            'annotation' => 'Аннотация',
            'excerpt' => 'Отрывок',
            'fragment' => 'Фрагмент',
            'is_featured' => false,
            'sort_order' => 0,
        ]);

        $book = Book::query()->where('slug', 'test-book')->firstOrFail();

        $response->assertRedirect(route('admin.books.edit', $book));
        $this->assertDatabaseHas('books', [
            'slug' => 'test-book',
            'price' => 59000,
            'status' => 'draft',
        ]);
    }

    public function test_new_book_is_always_created_with_draft_status(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/books', [
            'title' => 'Книга',
            'slug' => 'some-book',
            'price' => '100',
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('books', [
            'slug' => 'some-book',
            'status' => 'draft',
        ]);
    }

    public function test_price_is_stored_in_kopecks(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/books', [
            'title' => 'Книга цена',
            'slug' => 'price-book',
            'price' => '599.99',
            'status' => 'draft',
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('books', [
            'slug' => 'price-book',
            'price' => 59999,
        ]);
    }

    public function test_create_book_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/books', []);

        $response->assertSessionHasErrors(['title', 'slug', 'price']);
    }

    public function test_create_book_validates_unique_slug(): void
    {
        $admin = User::factory()->admin()->create();
        Book::factory()->create(['slug' => 'existing-slug']);

        $response = $this->actingAs($admin)->post('/admin/books', [
            'title' => 'Книга',
            'slug' => 'existing-slug',
            'price' => '100',
            'status' => 'draft',
            'sort_order' => 0,
        ]);

        $response->assertSessionHasErrors(['slug']);
    }

    public function test_cover_upload_dispatches_no_job_but_stores_on_s3_public(): void
    {
        Storage::fake('s3-public');

        $admin = User::factory()->admin()->create();

        $cover = UploadedFile::fake()->create('cover.jpg', 200, 'image/jpeg');

        $this->actingAs($admin)->post('/admin/books', [
            'title' => 'Книга с обложкой',
            'slug' => 'cover-book',
            'price' => '100',
            'status' => 'draft',
            'sort_order' => 0,
            'cover' => $cover,
        ]);

        $book = Book::query()->where('slug', 'cover-book')->firstOrFail();
        $this->assertNotNull($book->cover_path);
        Storage::disk('s3-public')->assertExists($book->cover_path);
    }

    public function test_epub_upload_dispatches_process_book_file_upload_job(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();

        $epub = UploadedFile::fake()->create('book.epub', 1024, 'application/epub+zip');

        $this->actingAs($admin)->post('/admin/books', [
            'title' => 'Книга с epub',
            'slug' => 'epub-book',
            'price' => '100',
            'sort_order' => 0,
            'epub' => $epub,
        ]);

        $book = Book::query()->where('slug', 'epub-book')->firstOrFail();

        Queue::assertPushed(ProcessBookFileUpload::class, function (ProcessBookFileUpload $job) use ($book): bool {
            return $job->bookId === $book->id;
        });
    }

    // -------------------------------------------------------------------------
    // Edit / Update book
    // -------------------------------------------------------------------------

    public function test_admin_can_update_a_book(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['slug' => 'original-slug', 'price' => 10000]);

        $response = $this->actingAs($admin)->put("/admin/books/{$book->slug}", [
            'title' => 'Обновлённое название',
            'slug' => 'updated-slug',
            'price' => '200',
            'status' => 'draft',
            'sort_order' => 1,
        ]);

        $book->refresh();
        $response->assertRedirect(route('admin.books.edit', $book));
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'slug' => 'updated-slug',
            'price' => 20000,
        ]);
    }

    public function test_update_allows_same_slug_on_current_book(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['slug' => 'my-slug']);

        $response = $this->actingAs($admin)->put("/admin/books/{$book->slug}", [
            'title' => 'То же название',
            'slug' => 'my-slug',
            'price' => '100',
            'status' => 'draft',
            'sort_order' => 0,
        ]);

        $book->refresh();
        $response->assertRedirect(route('admin.books.edit', $book));
    }

    public function test_update_cannot_publish_book_without_epub(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['status' => BookStatus::Draft, 'epub_path' => null]);

        $response = $this->actingAs($admin)->put("/admin/books/{$book->slug}", [
            'title' => $book->title,
            'slug' => $book->slug,
            'price' => '100',
            'status' => 'published',
            'sort_order' => 0,
        ]);

        $response->assertRedirect(route('admin.books.edit', $book));
        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'status' => 'draft']);
    }

    // -------------------------------------------------------------------------
    // Delete book — Rule 16, 17, 18
    // -------------------------------------------------------------------------

    public function test_admin_can_delete_draft_book_with_no_purchases(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['status' => BookStatus::Draft]);

        $response = $this->actingAs($admin)->delete("/admin/books/{$book->slug}");

        $response->assertRedirect('/admin/books');
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_admin_cannot_delete_published_book(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->published()->create();

        $this->actingAs($admin)->delete("/admin/books/{$book->slug}")
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('error', 'Нельзя удалить опубликованную книгу.');

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_admin_cannot_delete_draft_book_with_purchases(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['status' => BookStatus::Draft]);
        UserBook::factory()->create(['book_id' => $book->id]);

        $this->actingAs($admin)->delete("/admin/books/{$book->slug}")
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('error', 'Нельзя удалить книгу, у которой есть покупки.');

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    // -------------------------------------------------------------------------
    // Toggle status — Rule 15, 17
    // -------------------------------------------------------------------------

    public function test_toggle_status_publishes_draft_book_with_epub(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->withEpub()->create(['status' => BookStatus::Draft]);

        $this->actingAs($admin)->patch("/admin/books/{$book->slug}/toggle-status")
            ->assertJson(['status' => 'published']);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'status' => 'published']);
    }

    public function test_toggle_status_cannot_publish_draft_book_without_epub(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['status' => BookStatus::Draft, 'epub_path' => null]);

        $this->actingAs($admin)->patch("/admin/books/{$book->slug}/toggle-status")
            ->assertStatus(422)
            ->assertJson(['error' => 'Нельзя опубликовать книгу без файла epub.']);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'status' => 'draft']);
    }

    public function test_toggle_status_unpublishes_published_book_with_no_purchases(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->published()->create();

        $this->actingAs($admin)->patch("/admin/books/{$book->slug}/toggle-status")
            ->assertJson(['status' => 'draft']);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'status' => 'draft']);
    }

    // -------------------------------------------------------------------------
    // Toggle featured
    // -------------------------------------------------------------------------

    public function test_toggle_featured_flips_flag(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['is_featured' => false]);

        $this->actingAs($admin)->patch("/admin/books/{$book->slug}/toggle-featured")
            ->assertJson(['is_featured' => true]);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'is_featured' => true]);

        $this->actingAs($admin)->patch("/admin/books/{$book->slug}/toggle-featured")
            ->assertJson(['is_featured' => false]);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'is_featured' => false]);
    }

    // -------------------------------------------------------------------------
    // Edit page
    // -------------------------------------------------------------------------

    public function test_admin_can_access_edit_page(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $this->actingAs($admin)->get("/admin/books/{$book->slug}/edit")->assertStatus(200);
    }
}
