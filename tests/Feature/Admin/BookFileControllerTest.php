<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BookFileStatus;
use App\Features\Admin\Jobs\ConvertBookFormat;
use App\Features\Admin\Jobs\UploadSourceFile;
use App\Models\Book;
use App\Models\BookFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookFileControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Upload flow — source
    // -------------------------------------------------------------------------

    public function test_source_upload_creates_book_file_and_dispatches_job(): void
    {
        Bus::fake();
        Storage::fake('s3-private');

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $file = UploadedFile::fake()->create('book.epub', 100, 'application/epub+zip');

        $response = $this->actingAs($admin)->post(
            route('admin.books.files.store', $book),
            ['file' => $file],
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('book_files', [
            'book_id' => $book->id,
            'format' => 'epub',
            'is_source' => true,
            'status' => 'pending',
        ]);

        Bus::assertDispatched(UploadSourceFile::class);
        Bus::assertNotDispatched(ConvertBookFormat::class);
    }

    public function test_source_upload_dispatches_upload_source_file_job(): void
    {
        Bus::fake();
        Storage::fake('s3-private');

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        // Seed an existing derived FB2 file — it stays untouched until the job runs.
        BookFile::factory()->fb2()->ready()->create([
            'book_id' => $book->id,
            'is_source' => false,
            'path' => 'books/'.$book->id.'/old.fb2',
        ]);

        $file = UploadedFile::fake()->create('book.epub', 100, 'application/epub+zip');

        $this->actingAs($admin)->post(
            route('admin.books.files.store', $book),
            ['file' => $file],
        );

        // Derived files are reset asynchronously by dispatchConversions() inside the job.
        // Here we verify the job was dispatched — the job test covers the reset behaviour.
        Bus::assertDispatched(UploadSourceFile::class);
    }

    public function test_source_upload_replaces_existing_source_and_derived_records(): void
    {
        Bus::fake();
        Storage::fake('s3-private');

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        // Existing source + a derived file — both must be deleted on re-upload.
        $oldSource = BookFile::factory()->epub()->source()->create([
            'book_id' => $book->id,
            'path' => 'books/'.$book->id.'/source.epub',
        ]);
        $oldDerived = BookFile::factory()->fb2()->create([
            'book_id' => $book->id,
            'path' => 'books/'.$book->id.'/derived.fb2',
        ]);
        Storage::disk('s3-private')->put('books/'.$book->id.'/source.epub', 'old source');
        Storage::disk('s3-private')->put('books/'.$book->id.'/derived.fb2', 'old derived');

        $file = UploadedFile::fake()->create('book.epub', 100, 'application/epub+zip');

        $this->actingAs($admin)->post(
            route('admin.books.files.store', $book),
            ['file' => $file],
        );

        // Old records deleted, new source record created.
        $this->assertDatabaseMissing('book_files', ['id' => $oldSource->id]);
        $this->assertDatabaseMissing('book_files', ['id' => $oldDerived->id]);

        $newSource = BookFile::query()
            ->where('book_id', $book->id)
            ->where('is_source', true)
            ->firstOrFail();

        $this->assertEquals(BookFileStatus::Pending, $newSource->status);
        $this->assertNull($newSource->path);
        Bus::assertDispatched(UploadSourceFile::class, fn ($job) => $job->bookFileId === $newSource->id);
    }

    public function test_source_upload_allows_format_change_from_docx_to_fb2(): void
    {
        Bus::fake();
        Storage::fake('s3-private');

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        // Existing source is docx; derived fb2 exists — would cause unique constraint
        // violation if we tried to INSERT a new source fb2 without clearing first.
        BookFile::factory()->docx()->source()->create(['book_id' => $book->id]);
        BookFile::factory()->fb2()->create(['book_id' => $book->id]);

        $file = UploadedFile::fake()->create('book.fb2', 100, 'text/xml');

        $response = $this->actingAs($admin)->post(
            route('admin.books.files.store', $book),
            ['file' => $file],
        );

        $response->assertRedirect();
        $this->assertDatabaseCount('book_files', 1);
        $this->assertDatabaseHas('book_files', [
            'book_id' => $book->id,
            'format' => 'fb2',
            'is_source' => true,
            'status' => BookFileStatus::Pending->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Upload flow — derived format re-upload
    // -------------------------------------------------------------------------

    public function test_derived_format_upload_stores_directly_without_dispatching_job(): void
    {
        Bus::fake();
        Storage::fake('s3-private');

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $file = UploadedFile::fake()->create('book.epub', 100, 'application/epub+zip');

        $this->actingAs($admin)->post(
            route('admin.books.files.store', $book),
            ['file' => $file, 'format' => 'epub'],
        );

        $this->assertDatabaseHas('book_files', [
            'book_id' => $book->id,
            'format' => 'epub',
            'is_source' => false,
            'status' => 'ready',
        ]);

        Bus::assertNotDispatched(UploadSourceFile::class);
        Bus::assertNotDispatched(ConvertBookFormat::class);
    }

    public function test_derived_fb2_upload_stores_directly_and_marks_ready(): void
    {
        Bus::fake();
        Storage::fake('s3-private');

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $file = UploadedFile::fake()->create('book.fb2', 50, 'application/x-fictionbook+xml');

        $this->actingAs($admin)->post(
            route('admin.books.files.store', $book),
            ['file' => $file, 'format' => 'fb2'],
        );

        $this->assertDatabaseHas('book_files', [
            'book_id' => $book->id,
            'format' => 'fb2',
            'is_source' => false,
            'status' => 'ready',
        ]);
    }

    public function test_derived_format_upload_replaces_existing_record(): void
    {
        Bus::fake();
        Storage::fake('s3-private');

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $existing = BookFile::factory()->epub()->ready()->create([
            'book_id' => $book->id,
            'is_source' => false,
            'path' => 'books/'.$book->id.'/old.epub',
        ]);
        Storage::disk('s3-private')->put('books/'.$book->id.'/old.epub', 'old');

        $file = UploadedFile::fake()->create('new.epub', 100, 'application/epub+zip');

        $this->actingAs($admin)->post(
            route('admin.books.files.store', $book),
            ['file' => $file, 'format' => 'epub'],
        );

        // Old path deleted from S3
        Storage::disk('s3-private')->assertMissing('books/'.$book->id.'/old.epub');

        // Only one record for this format remains
        $this->assertDatabaseCount('book_files', 1);
        $existing->refresh();
        $this->assertEquals(BookFileStatus::Ready, $existing->status);
        $this->assertNotNull($existing->path);
    }

    // -------------------------------------------------------------------------
    // Retry flow
    // -------------------------------------------------------------------------

    public function test_retry_resets_failed_file_and_dispatches_job(): void
    {
        Bus::fake();

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $source = BookFile::factory()->epub()->source()->create(['book_id' => $book->id]);
        $failed = BookFile::factory()->fb2()->failed()->create([
            'book_id' => $book->id,
            'is_source' => false,
        ]);

        $this->actingAs($admin)->post(
            route('admin.books.files.retry', [$book, $failed]),
        )->assertRedirect();

        $failed->refresh();
        $this->assertEquals(BookFileStatus::Pending, $failed->status);
        $this->assertNull($failed->error_message);

        Bus::assertDispatched(ConvertBookFormat::class, function ($job) use ($failed, $source): bool {
            return $job->bookFileId === $failed->id && $job->sourceBookFileId === $source->id;
        });
    }

    public function test_retry_returns_422_for_non_failed_file(): void
    {
        Bus::fake();

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $readyFile = BookFile::factory()->epub()->ready()->create([
            'book_id' => $book->id,
            'is_source' => false,
        ]);

        $this->actingAs($admin)->post(
            route('admin.books.files.retry', [$book, $readyFile]),
        )->assertStatus(422);

        Bus::assertNotDispatched(ConvertBookFormat::class);
    }

    public function test_retry_returns_422_when_no_source_file_exists(): void
    {
        Bus::fake();

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $failed = BookFile::factory()->fb2()->failed()->create([
            'book_id' => $book->id,
            'is_source' => false,
        ]);

        // No source BookFile record exists.

        $this->actingAs($admin)->post(
            route('admin.books.files.retry', [$book, $failed]),
        )->assertStatus(422);

        Bus::assertNotDispatched(ConvertBookFormat::class);
    }

    // -------------------------------------------------------------------------
    // Admin download
    // -------------------------------------------------------------------------

    public function test_admin_can_download_any_format_including_docx(): void
    {
        Storage::fake('s3-private');

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $docxFile = BookFile::factory()->docx()->ready()->create([
            'book_id' => $book->id,
            'path' => 'books/'.$book->id.'/source.docx',
        ]);

        Storage::disk('s3-private')->put('books/'.$book->id.'/source.docx', 'docx content');

        $response = $this->actingAs($admin)->get(
            route('admin.books.files.download', [$book, $docxFile]),
        );

        // Should redirect to the temporary S3 URL (not 403 or 404).
        $response->assertRedirect();
        $this->assertStringNotContainsString('/login', $response->headers->get('Location') ?? '');
    }

    public function test_admin_download_returns_404_when_file_has_no_path(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $pendingFile = BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Pending,
            'path' => null,
        ]);

        $this->actingAs($admin)->get(
            route('admin.books.files.download', [$book, $pendingFile]),
        )->assertStatus(404);
    }

    public function test_admin_download_returns_404_for_wrong_book(): void
    {
        Storage::fake('s3-private');

        $admin = User::factory()->admin()->create();
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();

        $file = BookFile::factory()->epub()->ready()->create([
            'book_id' => $book2->id,
            'path' => 'books/'.$book2->id.'/file.epub',
        ]);

        $this->actingAs($admin)->get(
            route('admin.books.files.download', [$book1, $file]),
        )->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Status endpoint
    // -------------------------------------------------------------------------

    public function test_status_returns_json_array_of_all_book_files(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        BookFile::factory()->epub()->ready()->create(['book_id' => $book->id]);
        BookFile::factory()->fb2()->failed()->create(['book_id' => $book->id]);

        $response = $this->actingAs($admin)->getJson(
            route('admin.books.files.status', $book),
        );

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonStructure([
            '*' => ['id', 'format', 'status', 'error_message', 'updated_at'],
        ]);
    }

    public function test_status_returns_correct_format_and_status_values(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        BookFile::factory()->epub()->ready()->create([
            'book_id' => $book->id,
            'error_message' => null,
        ]);

        $response = $this->actingAs($admin)->getJson(
            route('admin.books.files.status', $book),
        );

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'format' => 'epub',
            'status' => 'ready',
            'error_message' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Publish guard — book requires ready client file
    // -------------------------------------------------------------------------

    public function test_cannot_publish_book_without_ready_client_file(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['status' => 'draft']);

        // Only a pending file — not ready.
        BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Pending,
        ]);

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

    public function test_can_publish_book_with_ready_epub_file(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['status' => 'draft']);

        BookFile::factory()->epub()->ready()->create(['book_id' => $book->id]);

        $response = $this->actingAs($admin)->put("/admin/books/{$book->slug}", [
            'title' => $book->title,
            'slug' => $book->slug,
            'price' => '100',
            'status' => 'published',
            'sort_order' => 0,
        ]);

        $response->assertRedirect(route('admin.books.edit', $book));
        $this->assertDatabaseHas('books', ['id' => $book->id, 'status' => 'published']);
    }

    public function test_cannot_publish_book_with_ready_docx_only(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['status' => 'draft']);

        // DOCX is not client-accessible — hasClientReadyFile() should return false.
        BookFile::factory()->docx()->ready()->create(['book_id' => $book->id]);

        $response = $this->actingAs($admin)->put("/admin/books/{$book->slug}", [
            'title' => $book->title,
            'slug' => $book->slug,
            'price' => '100',
            'status' => 'published',
            'sort_order' => 0,
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'status' => 'draft']);
    }

    // -------------------------------------------------------------------------
    // Delete book — S3 cleanup
    // -------------------------------------------------------------------------

    public function test_delete_book_removes_s3_files_before_db_record(): void
    {
        Storage::fake('s3-private');
        Storage::fake('s3-public');

        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['status' => 'draft']);

        $path = 'books/'.$book->id.'/source.epub';
        Storage::disk('s3-private')->put($path, 'epub content');

        BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'path' => $path,
            'status' => BookFileStatus::Ready,
            'is_source' => true,
        ]);

        $this->actingAs($admin)->delete("/admin/books/{$book->slug}")->assertRedirect('/admin/books');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        Storage::disk('s3-private')->assertMissing($path);
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_book_file_routes(): void
    {
        $book = Book::factory()->create();

        $this->post(route('admin.books.files.store', $book))->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_book_file_routes(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.books.files.store', $book))
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Request validation
    // -------------------------------------------------------------------------

    public function test_store_validates_required_file(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.books.files.store', $book), [])
            ->assertSessionHasErrors(['file']);
    }

    public function test_store_rejects_invalid_format_value(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create();

        $file = UploadedFile::fake()->create('book.epub', 100, 'application/epub+zip');

        $this->actingAs($admin)
            ->post(route('admin.books.files.store', $book), ['file' => $file, 'format' => 'docx'])
            ->assertSessionHasErrors(['format']);
    }
}
