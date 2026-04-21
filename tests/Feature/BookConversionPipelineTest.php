<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookFileFormat;
use App\Enums\BookFileStatus;
use App\Features\Admin\Contracts\FormatConverter;
use App\Features\Admin\Exceptions\ConversionException;
use App\Features\Admin\Jobs\ConvertBookFormat;
use App\Features\Admin\Services\BookConversionService;
use App\Features\Admin\Services\Converters\CalibreConverter;
use App\Features\Admin\Services\Converters\PandocConverter;
use App\Models\Book;
use App\Models\BookFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class BookConversionPipelineTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // PandocConverter::supports()
    // -------------------------------------------------------------------------

    public function test_pandoc_supports_docx_to_epub(): void
    {
        $converter = new PandocConverter;

        $this->assertTrue($converter->supports(BookFileFormat::Docx, BookFileFormat::Epub));
    }

    public function test_pandoc_does_not_support_docx_to_fb2(): void
    {
        $converter = new PandocConverter;

        $this->assertFalse($converter->supports(BookFileFormat::Docx, BookFileFormat::Fb2));
    }

    public function test_pandoc_does_not_support_epub_to_fb2(): void
    {
        $converter = new PandocConverter;

        $this->assertFalse($converter->supports(BookFileFormat::Epub, BookFileFormat::Fb2));
    }

    public function test_pandoc_does_not_support_fb2_to_epub(): void
    {
        $converter = new PandocConverter;

        $this->assertFalse($converter->supports(BookFileFormat::Fb2, BookFileFormat::Epub));
    }

    // -------------------------------------------------------------------------
    // CalibreConverter::supports()
    // -------------------------------------------------------------------------

    public function test_calibre_supports_docx_to_fb2(): void
    {
        $converter = new CalibreConverter;

        $this->assertTrue($converter->supports(BookFileFormat::Docx, BookFileFormat::Fb2));
    }

    public function test_calibre_supports_epub_to_fb2(): void
    {
        $converter = new CalibreConverter;

        $this->assertTrue($converter->supports(BookFileFormat::Epub, BookFileFormat::Fb2));
    }

    public function test_calibre_supports_fb2_to_epub(): void
    {
        $converter = new CalibreConverter;

        $this->assertTrue($converter->supports(BookFileFormat::Fb2, BookFileFormat::Epub));
    }

    public function test_calibre_does_not_support_docx_to_epub(): void
    {
        $converter = new CalibreConverter;

        $this->assertFalse($converter->supports(BookFileFormat::Docx, BookFileFormat::Epub));
    }

    // -------------------------------------------------------------------------
    // BookConversionService::resolveConverter()
    // -------------------------------------------------------------------------

    public function test_resolve_converter_returns_pandoc_for_docx_to_epub(): void
    {
        $service = new BookConversionService;

        $converter = $service->resolveConverter(BookFileFormat::Docx, BookFileFormat::Epub);

        $this->assertInstanceOf(PandocConverter::class, $converter);
    }

    public function test_resolve_converter_returns_calibre_for_docx_to_fb2(): void
    {
        $service = new BookConversionService;

        $converter = $service->resolveConverter(BookFileFormat::Docx, BookFileFormat::Fb2);

        $this->assertInstanceOf(CalibreConverter::class, $converter);
    }

    public function test_resolve_converter_returns_calibre_for_epub_to_fb2(): void
    {
        $service = new BookConversionService;

        $converter = $service->resolveConverter(BookFileFormat::Epub, BookFileFormat::Fb2);

        $this->assertInstanceOf(CalibreConverter::class, $converter);
    }

    public function test_resolve_converter_returns_calibre_for_fb2_to_epub(): void
    {
        $service = new BookConversionService;

        $converter = $service->resolveConverter(BookFileFormat::Fb2, BookFileFormat::Epub);

        $this->assertInstanceOf(CalibreConverter::class, $converter);
    }

    public function test_resolve_converter_defaults_to_calibre_when_not_in_config(): void
    {
        config(['bookshop.formats.converter_preference.epub_to_fb2' => null]);

        $service = new BookConversionService;

        // Calibre supports epub->fb2 so it should be returned as fallback.
        $converter = $service->resolveConverter(BookFileFormat::Epub, BookFileFormat::Fb2);

        $this->assertInstanceOf(CalibreConverter::class, $converter);
    }

    public function test_resolve_converter_throws_when_no_converter_supports_pair(): void
    {
        // EPUB → DOCX is not supported by any converter.
        $service = new BookConversionService;

        $this->expectException(\RuntimeException::class);

        $service->resolveConverter(BookFileFormat::Epub, BookFileFormat::Docx);
    }

    // -------------------------------------------------------------------------
    // BookConversionService::dispatchConversions()
    // -------------------------------------------------------------------------

    public function test_dispatch_conversions_creates_target_records_and_dispatches_jobs(): void
    {
        Queue::fake();
        Storage::fake('s3-private');

        $book = Book::factory()->create();
        $source = BookFile::factory()->docx()->create([
            'book_id' => $book->id,
            'is_source' => true,
            'status' => BookFileStatus::Ready,
            'path' => 'books/1/source.docx',
        ]);

        $service = new BookConversionService;
        $service->dispatchConversions($source);

        // DOCX matrix: epub, fb2 — two jobs dispatched.
        Queue::assertCount(2);
        Queue::assertPushed(ConvertBookFormat::class, 2);

        $this->assertDatabaseHas('book_files', [
            'book_id' => $book->id,
            'format' => BookFileFormat::Epub->value,
            'status' => BookFileStatus::Pending->value,
            'is_source' => false,
        ]);

        $this->assertDatabaseHas('book_files', [
            'book_id' => $book->id,
            'format' => BookFileFormat::Fb2->value,
            'status' => BookFileStatus::Pending->value,
            'is_source' => false,
        ]);
    }

    public function test_dispatch_conversions_reuses_existing_target_and_resets_status(): void
    {
        Queue::fake();
        Storage::fake('s3-private');

        $book = Book::factory()->create();
        $source = BookFile::factory()->docx()->create([
            'book_id' => $book->id,
            'is_source' => true,
            'status' => BookFileStatus::Ready,
            'path' => 'books/1/source.docx',
        ]);

        // Pre-create a target epub record in ready state.
        $existingEpub = BookFile::factory()->epub()->ready()->create([
            'book_id' => $book->id,
            'is_source' => false,
        ]);
        $originalId = $existingEpub->id;

        $service = new BookConversionService;
        $service->dispatchConversions($source);

        // The existing epub record should be reused (same id).
        $existingEpub->refresh();

        $this->assertSame($originalId, $existingEpub->id);
        $this->assertEquals(BookFileStatus::Pending, $existingEpub->status);
        $this->assertNull($existingEpub->path);
        $this->assertNull($existingEpub->error_message);
    }

    public function test_dispatch_conversions_deletes_old_s3_file_when_reusing_target(): void
    {
        Queue::fake();
        Storage::fake('s3-private');

        $book = Book::factory()->create();
        $source = BookFile::factory()->docx()->create([
            'book_id' => $book->id,
            'is_source' => true,
            'status' => BookFileStatus::Ready,
            'path' => 'books/1/source.docx',
        ]);

        $oldPath = 'books/1/old-epub.epub';
        Storage::disk('s3-private')->put($oldPath, 'old content');

        BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'is_source' => false,
            'status' => BookFileStatus::Ready,
            'path' => $oldPath,
        ]);

        $service = new BookConversionService;
        $service->dispatchConversions($source);

        Storage::disk('s3-private')->assertMissing($oldPath);
    }

    public function test_dispatch_conversions_dispatches_job_with_correct_ids(): void
    {
        Queue::fake();
        Storage::fake('s3-private');

        $book = Book::factory()->create();
        $source = BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'is_source' => true,
            'status' => BookFileStatus::Ready,
            'path' => 'books/1/source.epub',
        ]);

        $service = new BookConversionService;
        $service->dispatchConversions($source);

        // EPUB matrix: fb2 only — one job.
        Queue::assertPushed(ConvertBookFormat::class, function (ConvertBookFormat $job) use ($source) {
            return $job->sourceBookFileId === $source->id;
        });
    }

    // -------------------------------------------------------------------------
    // ConvertBookFormat job — happy path
    // -------------------------------------------------------------------------

    public function test_convert_book_format_job_marks_target_ready_on_success(): void
    {
        Storage::fake('s3-private');

        $book = Book::factory()->create();
        $source = BookFile::factory()->docx()->ready()->create([
            'book_id' => $book->id,
            'is_source' => true,
        ]);
        Storage::disk('s3-private')->put($source->path, 'docx content');

        $target = BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Pending,
            'path' => null,
        ]);

        $mockConverter = $this->createMock(FormatConverter::class);
        $mockConverter->method('supports')->willReturn(true);
        $mockConverter->method('convert')->willReturnCallback(
            function (string $input, string $output): void {
                file_put_contents($output, 'epub content');
            }
        );

        $service = $this->getMockBuilder(BookConversionService::class)
            ->onlyMethods(['resolveConverter'])
            ->getMock();
        $service->method('resolveConverter')->willReturn($mockConverter);

        $job = new ConvertBookFormat($target->id, $source->id);
        $job->handle($service);

        $target->refresh();

        $this->assertEquals(BookFileStatus::Ready, $target->status);
        $this->assertNotNull($target->path);
        Storage::disk('s3-private')->assertExists($target->path);
    }

    public function test_convert_book_format_job_aborts_silently_when_target_not_found(): void
    {
        Storage::fake('s3-private');

        $service = $this->createMock(BookConversionService::class);
        $service->expects($this->never())->method('executeConversion');

        $job = new ConvertBookFormat(999999, 1);
        $job->handle($service);
    }

    public function test_convert_book_format_job_aborts_silently_when_target_not_pending(): void
    {
        Storage::fake('s3-private');

        $target = BookFile::factory()->epub()->create(['status' => BookFileStatus::Processing]);

        $service = $this->createMock(BookConversionService::class);
        $service->expects($this->never())->method('executeConversion');

        $job = new ConvertBookFormat($target->id, 1);
        $job->handle($service);
    }

    public function test_convert_book_format_job_marks_failed_when_source_has_no_path(): void
    {
        Storage::fake('s3-private');

        $book = Book::factory()->create();
        $source = BookFile::factory()->docx()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Ready,
            'path' => null,
        ]);
        $target = BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Pending,
        ]);

        $service = $this->createMock(BookConversionService::class);
        $service->expects($this->never())->method('executeConversion');

        $job = new ConvertBookFormat($target->id, $source->id);
        $job->handle($service);

        $target->refresh();

        $this->assertEquals(BookFileStatus::Failed, $target->status);
        $this->assertNotNull($target->error_message);
    }

    public function test_convert_book_format_job_marks_failed_when_source_not_ready(): void
    {
        Storage::fake('s3-private');

        $book = Book::factory()->create();
        $source = BookFile::factory()->docx()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Processing,
            'path' => 'books/1/source.docx',
        ]);
        $target = BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Pending,
        ]);

        $service = $this->createMock(BookConversionService::class);
        $service->expects($this->never())->method('executeConversion');

        $job = new ConvertBookFormat($target->id, $source->id);
        $job->handle($service);

        $target->refresh();

        $this->assertEquals(BookFileStatus::Failed, $target->status);
    }

    public function test_convert_book_format_job_marks_failed_when_conversion_throws(): void
    {
        Storage::fake('s3-private');

        $book = Book::factory()->create();
        $source = BookFile::factory()->docx()->ready()->create(['book_id' => $book->id]);
        Storage::disk('s3-private')->put($source->path, 'docx content');

        $target = BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Pending,
        ]);

        $service = $this->getMockBuilder(BookConversionService::class)
            ->onlyMethods(['resolveConverter', 'executeConversion'])
            ->getMock();
        $service->method('executeConversion')
            ->willThrowException(new ConversionException('pandoc failed: some stderr'));

        $job = new ConvertBookFormat($target->id, $source->id);
        $job->handle($service);

        $target->refresh();

        $this->assertEquals(BookFileStatus::Failed, $target->status);
        $this->assertStringContainsString('pandoc failed', $target->error_message);
    }

    public function test_convert_book_format_job_truncates_error_message_to_2000_chars(): void
    {
        Storage::fake('s3-private');

        $book = Book::factory()->create();
        $source = BookFile::factory()->docx()->ready()->create(['book_id' => $book->id]);
        Storage::disk('s3-private')->put($source->path, 'docx content');

        $target = BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Pending,
        ]);

        $longError = str_repeat('x', 5000);

        $service = $this->getMockBuilder(BookConversionService::class)
            ->onlyMethods(['executeConversion'])
            ->getMock();
        $service->method('executeConversion')
            ->willThrowException(new ConversionException($longError));

        $job = new ConvertBookFormat($target->id, $source->id);
        $job->handle($service);

        $target->refresh();

        $this->assertLessThanOrEqual(2000, mb_strlen($target->error_message));
    }

    public function test_convert_book_format_job_aborts_silently_on_race_condition(): void
    {
        Storage::fake('s3-private');

        $book = Book::factory()->create();
        $source = BookFile::factory()->docx()->ready()->create(['book_id' => $book->id]);
        Storage::disk('s3-private')->put($source->path, 'docx content');

        $target = BookFile::factory()->epub()->create([
            'book_id' => $book->id,
            'status' => BookFileStatus::Pending,
        ]);

        $service = $this->getMockBuilder(BookConversionService::class)
            ->onlyMethods(['executeConversion'])
            ->getMock();

        // Simulate a race: executeConversion throws, and in the catch block
        // we bump source->updated_at so the guard triggers.
        $service->method('executeConversion')
            ->willReturnCallback(function () use ($source): void {
                // Move updated_at forward to simulate a re-upload.
                $source->updated_at = now()->addMinute();
                $source->saveQuietly();

                throw new ConversionException('conversion failed mid-way');
            });

        $job = new ConvertBookFormat($target->id, $source->id);
        $job->handle($service);

        $target->refresh();

        // Status must NOT be failed — job aborted silently.
        $this->assertNotEquals(BookFileStatus::Failed, $target->status);
    }

    // -------------------------------------------------------------------------
    // Integration: convert() throws on binary integration test (skipped in CI)
    // -------------------------------------------------------------------------

    #[Group('conversion-integration')]
    public function test_pandoc_convert_integration(): void
    {
        if (! $this->binaryAvailable('pandoc')) {
            $this->markTestSkipped('Requires Pandoc installed');
        }

        $tmpDir = sys_get_temp_dir();
        $input = $tmpDir.'/bookshop_test_'.uniqid().'.docx';
        $output = $tmpDir.'/bookshop_test_'.uniqid().'.epub';

        // Produce a real DOCX from markdown using pandoc directly, then convert it via PandocConverter.
        $md = $tmpDir.'/bookshop_test_'.uniqid().'.md';
        file_put_contents($md, "# Test\n\nHello world.");
        exec("pandoc -f markdown {$md} -o {$input}", result_code: $code);
        @unlink($md);

        if ($code !== 0 || ! file_exists($input)) {
            $this->markTestSkipped('Could not generate test DOCX via pandoc');
        }

        try {
            $converter = new PandocConverter;
            $converter->convert($input, $output, BookFileFormat::Docx, BookFileFormat::Epub);
            $this->assertFileExists($output);
            $this->assertGreaterThan(0, filesize($output));
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    #[Group('conversion-integration')]
    public function test_calibre_convert_integration(): void
    {
        if (! $this->binaryAvailable('ebook-convert')) {
            $this->markTestSkipped('Requires Calibre installed');
        }

        if (! $this->binaryAvailable('pandoc')) {
            $this->markTestSkipped('Calibre integration test requires Pandoc to produce source EPUB');
        }

        $tmpDir = sys_get_temp_dir();
        $docx = $tmpDir.'/bookshop_test_'.uniqid().'.docx';
        $epub = $tmpDir.'/bookshop_test_'.uniqid().'.epub';
        $fb2 = $tmpDir.'/bookshop_test_'.uniqid().'.fb2';

        // Produce DOCX → EPUB via pandoc, then EPUB → FB2 via Calibre.
        $md = $tmpDir.'/bookshop_test_'.uniqid().'.md';
        file_put_contents($md, "# Test\n\nHello world.");
        exec("pandoc -f markdown {$md} -o {$docx}", result_code: $code);
        @unlink($md);

        if ($code !== 0 || ! file_exists($docx)) {
            $this->markTestSkipped('Could not generate test DOCX via pandoc');
        }

        try {
            (new PandocConverter)->convert($docx, $epub, BookFileFormat::Docx, BookFileFormat::Epub);

            $converter = new CalibreConverter;
            $converter->convert($epub, $fb2, BookFileFormat::Epub, BookFileFormat::Fb2);
            $this->assertFileExists($fb2);
            $this->assertGreaterThan(0, filesize($fb2));
        } finally {
            @unlink($docx);
            @unlink($epub);
            @unlink($fb2);
        }
    }

    private function binaryAvailable(string $binary): bool
    {
        exec("which {$binary} 2>/dev/null", $out, $code);

        return $code === 0;
    }
}
