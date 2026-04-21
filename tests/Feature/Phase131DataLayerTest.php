<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookFileFormat;
use App\Enums\BookFileStatus;
use App\Models\Book;
use App\Models\BookFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase131DataLayerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // BookFileFormat enum
    // -------------------------------------------------------------------------

    public function test_docx_is_not_client_accessible(): void
    {
        $this->assertFalse(BookFileFormat::Docx->isClientAccessible());
    }

    public function test_epub_is_client_accessible(): void
    {
        $this->assertTrue(BookFileFormat::Epub->isClientAccessible());
    }

    public function test_fb2_is_client_accessible(): void
    {
        $this->assertTrue(BookFileFormat::Fb2->isClientAccessible());
    }

    public function test_client_accessible_returns_epub_and_fb2(): void
    {
        $accessible = BookFileFormat::clientAccessible();

        $this->assertCount(2, $accessible);
        $this->assertContains(BookFileFormat::Epub, $accessible);
        $this->assertContains(BookFileFormat::Fb2, $accessible);
        $this->assertNotContains(BookFileFormat::Docx, $accessible);
    }

    public function test_format_labels(): void
    {
        $this->assertSame('DOCX', BookFileFormat::Docx->label());
        $this->assertSame('EPUB', BookFileFormat::Epub->label());
        $this->assertSame('FB2', BookFileFormat::Fb2->label());
    }

    public function test_format_extensions(): void
    {
        $this->assertSame('docx', BookFileFormat::Docx->extension());
        $this->assertSame('epub', BookFileFormat::Epub->extension());
        $this->assertSame('fb2', BookFileFormat::Fb2->extension());
    }

    public function test_format_mime_types(): void
    {
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            BookFileFormat::Docx->mimeType(),
        );
        $this->assertSame('application/epub+zip', BookFileFormat::Epub->mimeType());
        $this->assertSame('application/x-fictionbook+xml', BookFileFormat::Fb2->mimeType());
    }

    // -------------------------------------------------------------------------
    // BookFile model — relationships and helper methods
    // -------------------------------------------------------------------------

    public function test_book_file_belongs_to_book(): void
    {
        $book = Book::factory()->create();
        $bookFile = BookFile::factory()->epub()->create(['book_id' => $book->id]);

        $this->assertTrue($bookFile->book->is($book));
    }

    public function test_book_has_many_files(): void
    {
        $book = Book::factory()->create();
        BookFile::factory()->epub()->create(['book_id' => $book->id]);
        BookFile::factory()->fb2()->create(['book_id' => $book->id]);

        $this->assertCount(2, $book->files);
    }

    public function test_is_ready_returns_true_when_status_is_ready(): void
    {
        $bookFile = BookFile::factory()->epub()->ready()->create();

        $this->assertTrue($bookFile->isReady());
    }

    public function test_is_ready_returns_false_when_status_is_pending(): void
    {
        $bookFile = BookFile::factory()->epub()->create(['status' => BookFileStatus::Pending]);

        $this->assertFalse($bookFile->isReady());
    }

    public function test_is_source_returns_true_for_source_file(): void
    {
        $bookFile = BookFile::factory()->source()->create();

        $this->assertTrue($bookFile->isSource());
    }

    public function test_is_source_returns_false_for_derived_file(): void
    {
        $bookFile = BookFile::factory()->epub()->create(['is_source' => false]);

        $this->assertFalse($bookFile->isSource());
    }

    public function test_is_client_accessible_delegates_to_format(): void
    {
        $epubFile = BookFile::factory()->epub()->create();
        $docxFile = BookFile::factory()->docx()->create();

        $this->assertTrue($epubFile->isClientAccessible());
        $this->assertFalse($docxFile->isClientAccessible());
    }

    // -------------------------------------------------------------------------
    // Book::hasClientReadyFile()
    // -------------------------------------------------------------------------

    public function test_has_client_ready_file_returns_false_with_no_files(): void
    {
        $book = Book::factory()->create();

        $this->assertFalse($book->hasClientReadyFile());
    }

    public function test_has_client_ready_file_returns_false_with_only_pending_files(): void
    {
        $book = Book::factory()->create();
        BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Pending,
        ]);

        $this->assertFalse($book->hasClientReadyFile());
    }

    public function test_has_client_ready_file_returns_false_with_only_failed_files(): void
    {
        $book = Book::factory()->create();
        BookFile::factory()->epub()->failed()->create(['book_id' => $book->id]);

        $this->assertFalse($book->hasClientReadyFile());
    }

    public function test_has_client_ready_file_returns_false_with_only_docx_ready(): void
    {
        $book = Book::factory()->create();
        BookFile::factory()->docx()->ready()->create(['book_id' => $book->id]);

        $this->assertFalse($book->hasClientReadyFile());
    }

    public function test_has_client_ready_file_returns_true_with_epub_ready(): void
    {
        $book = Book::factory()->create();
        BookFile::factory()->epub()->ready()->create(['book_id' => $book->id]);

        $this->assertTrue($book->hasClientReadyFile());
    }

    public function test_has_client_ready_file_returns_true_with_fb2_ready(): void
    {
        $book = Book::factory()->create();
        BookFile::factory()->fb2()->ready()->create(['book_id' => $book->id]);

        $this->assertTrue($book->hasClientReadyFile());
    }
}
