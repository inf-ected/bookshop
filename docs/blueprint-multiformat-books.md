# Blueprint — Multi-Format Books (Phase 13)

**Project**: Single-author Russian-language digital bookshop
**Stack**: Laravel 12, PHP 8.4, MySQL 8, Redis, Docker, S3 (MinIO), Calibre, Pandoc
**Last updated**: 2026-04-19

---

## Locked Answers to Clarifying Questions

| # | Question | Answer | Schema impact |
|---|----------|--------|---------------|
| 1 | Supported formats | DOCX (source only), EPUB (client), FB2 (client) | `BookFileFormat` enum |
| 2 | DOCX reachable by clients? | Never. Triple gate: enum, controller, service | No — enforcement only |
| 3 | Conversion tool selection | Pandoc for DOCX->EPUB; Calibre for all others | Config-driven in `config/bookshop.php` |
| 4 | Conversion queue | Default queue (no separate `conversions` queue) | No |
| 5 | `epub_path` column fate | Drop it. No data migration needed (no books in production) | Migration to drop column |
| 6 | Re-upload behavior | Replaces existing file. No versioning | No additional columns |
| 7 | Publish guard | Book requires at least one client-accessible `BookFile` with `status=ready` | No — runtime check |
| 8 | Client download default format | EPUB (via `?format=` query param) | No |
| 9 | Converter container strategy | Pandoc and Calibre installed in `docker/php/Dockerfile` (no separate container) | No |
| 10 | `ProcessBookFileUpload` job | Replaced by new `UploadSourceFile` and `ConvertBookFormat` jobs | Delete old job |

---

## Schema

### New table: `book_files`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UNSIGNED BIGINT AUTO_INCREMENT | NO | — | PK |
| `book_id` | UNSIGNED BIGINT | NO | — | FK to books.id |
| `format` | VARCHAR(10) | NO | — | BookFileFormat enum: docx, epub, fb2 |
| `status` | VARCHAR(20) | NO | 'pending' | BookFileStatus enum: pending, processing, ready, failed |
| `path` | VARCHAR(255) | YES | NULL | S3 private disk path; null until upload completes |
| `is_source` | TINYINT(1) | NO | 0 | True if this is the original uploaded file (not derived) |
| `error_message` | TEXT | YES | NULL | Conversion error output (populated on failure) |
| `created_at` | TIMESTAMP | YES | NULL | Laravel timestamp |
| `updated_at` | TIMESTAMP | YES | NULL | Laravel timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE INDEX `book_files_book_id_format_unique` (`book_id`, `format`) — one file per format per book
- INDEX `book_files_status_index` (`status`)

**Foreign keys:**
- `book_id` REFERENCES `books`(`id`) ON DELETE CASCADE

### Modified table: `books`

- DROP column `epub_path`

### Modified table: `download_logs`

- ADD column `format` VARCHAR(10), NOT NULL, DEFAULT `'epub'` — records which format was downloaded

---

## Enums

**BookFileFormat** (`App\Enums\BookFileFormat`) — string-backed

| Case | Value | Notes |
|------|-------|-------|
| `Docx` | `docx` | Source only — never delivered to clients |
| `Epub` | `epub` | Client deliverable |
| `Fb2` | `fb2` | Client deliverable |

Methods:
- `isClientAccessible(): bool` — returns `true` for Epub and Fb2, `false` for Docx. **This is the single authoritative gate for the DOCX protection rule.**
- `label(): string` — human-readable label for admin UI (DOCX, EPUB, FB2)
- `extension(): string` — file extension string (docx, epub, fb2)
- `mimeType(): string` — MIME type for each format
- `static clientAccessible(): array` — returns `[BookFileFormat::Epub, BookFileFormat::Fb2]`; used in queries that need to filter to client-accessible formats without hardcoding values

**BookFileStatus** (`App\Enums\BookFileStatus`) — string-backed

| Case | Value |
|------|-------|
| `Pending` | `pending` |
| `Processing` | `processing` |
| `Ready` | `ready` |
| `Failed` | `failed` |

---

## Relationships

| Parent | Relation | Child | FK | Notes |
|--------|----------|-------|-----|-------|
| Book | `hasMany` | BookFile | `book_files.book_id` | One book has up to 3 files (one per format) |
| BookFile | `belongsTo` | Book | `book_files.book_id` | |
| DownloadLog | — | — | — | Gains `format` column but no new FK |

**Book model additions:**
- `files(): HasMany` — `$this->hasMany(BookFile::class)` — the only true relationship; use with `->with('files')` for eager loading
- `hasClientReadyFile(): bool` — `$this->files()->where('status', BookFileStatus::Ready)->whereIn('format', BookFileFormat::clientAccessible())->exists()`. Used for publish guard and download availability checks.

Note: `readyFiles()` and `clientFiles()` are **not** defined as HasMany relations. Filtered HasMany relations behave unexpectedly when used with `load()` or `with()` under eager loading constraints — they silently return all records instead of filtered ones when the relation is already loaded. Use inline query builders in methods like `hasClientReadyFile()` instead.

**BookFile model:**
- `book(): BelongsTo`
- `isReady(): bool`
- `isSource(): bool`
- `isClientAccessible(): bool` — delegates to `$this->format->isClientAccessible()`

---

## Config: `config/bookshop.php` additions

```php
'formats' => [
    'conversion_matrix' => [
        'docx' => ['epub', 'fb2'],
        'epub' => ['fb2'],
        'fb2'  => ['epub'],
    ],

    'converter_preference' => [
        'docx_to_epub' => 'pandoc',
        'docx_to_fb2'  => 'calibre',
        'epub_to_fb2'  => 'calibre',
        'fb2_to_epub'  => 'calibre',
    ],
],
```

The matrix is keyed by source format; values are arrays of target formats that can be derived from it. `converter_preference` maps each `{source}_to_{target}` pair to the preferred converter tool. Adding a new format means adding entries here — no code changes required.

---

## Conversion Pipeline

### Contract: `FormatConverter`

Namespace: `App\Features\Admin\Contracts\FormatConverter`

```php
interface FormatConverter
{
    public function convert(string $inputPath, string $outputPath, BookFileFormat $from, BookFileFormat $to): void;
    public function supports(BookFileFormat $from, BookFileFormat $to): bool;
}
```

- `convert()` receives absolute local file paths. Throws `ConversionException` (a custom runtime exception) on failure, with stderr output captured in the message.
- `supports()` returns whether this converter can handle the given pair.

### Implementations

**PandocConverter** (`App\Features\Admin\Services\Converters\PandocConverter`)
- Wraps `pandoc` CLI via `Symfony\Component\Process\Process`
- Used for: DOCX -> EPUB
- Command: `pandoc input.docx -o output.epub`
- Timeout: 120 seconds (configurable)

**CalibreConverter** (`App\Features\Admin\Services\Converters\CalibreConverter`)
- Wraps `ebook-convert` CLI (Calibre) via `Symfony\Component\Process\Process`
- Used for: DOCX -> FB2, EPUB -> FB2, FB2 -> EPUB
- Command: `ebook-convert input.ext output.ext`
- Timeout: 120 seconds (configurable)

### Service: `BookConversionService`

Namespace: `App\Features\Admin\Services\BookConversionService`

Responsibilities:
1. Given a BookFile (source), determine which target formats can be derived (from config matrix)
2. Create `book_files` records for each target format with `status=pending`
3. Dispatch `ConvertBookFormat` jobs for each target
4. Resolve the correct converter implementation for a given format pair (from config preference)
5. Execute a single conversion: download source from S3 to temp dir, run converter, upload result to S3, update BookFile status

### Job: `ConvertBookFormat`

Namespace: `App\Features\Admin\Jobs\ConvertBookFormat`

| Property | Value |
|----------|-------|
| Queue | `default` |
| Tries | 2 |
| Backoff | 60 seconds |
| Timeout | 300 seconds |

Payload: `bookFileId` (the target BookFile ID, status=pending), `sourceBookFileId` (the source BookFile ID with the file to convert from).

Steps:
1. Load target BookFile; abort silently if not found or status is not `pending`
2. Set target status to `processing`
3. Load source BookFile; if source has no path or is not `ready`, mark target as `failed` with error message
4. Download source file from S3 to local temp directory
5. Resolve converter via `BookConversionService::resolveConverter()`
6. Run conversion
7. Upload output file to S3 private disk at `books/{book_id}/{uuid}.{ext}`
8. Update target BookFile: `path`, `status=ready`
9. Clean up temp files
10. On failure: set target status to `failed`, store error in `error_message`, clean up temp files

### Job: `UploadSourceFile`

Namespace: `App\Features\Admin\Jobs\UploadSourceFile`

Replaces the old `ProcessBookFileUpload` job.

| Property | Value |
|----------|-------|
| Queue | `default` |
| Tries | 3 |
| Backoff | 30 seconds |

Payload: `bookFileId`, `tempPath` (absolute path to the temp file on local disk).

Steps:
1. Load BookFile; abort silently if not found
2. Upload file from `tempPath` to S3 private disk at `books/{book_id}/{uuid}.{ext}`
3. Update BookFile: `path`, `status=ready`
4. Clean up temp file
5. Call `BookConversionService::dispatchConversions(BookFile $source)` to create derived format records and dispatch conversion jobs
6. On failure: set BookFile status to `failed`, clean up temp file

---

## Routes

### Admin routes (all: `web, auth, admin`)

| Method | URI | Controller@method | Middleware | Notes |
|--------|-----|-------------------|------------|-------|
| POST | `/admin/books/{book}/files` | Admin\BookFileController@store | web, auth, admin | Upload source or re-upload specific format |
| GET | `/admin/books/{book}/files/{bookFile}/download` | Admin\BookFileController@download | web, auth, admin | Admin downloads any format (incl. DOCX) for review |
| POST | `/admin/books/{book}/files/{bookFile}/retry` | Admin\BookFileController@retry | web, auth, admin | Re-dispatch conversion for a failed file |
| GET | `/admin/books/{book}/files/status` | Admin\BookFileController@status | web, auth, admin | JSON endpoint for polling file statuses |

### Client routes

| Method | URI | Controller@method | Middleware | Notes |
|--------|-----|-------------------|------------|-------|
| GET | `/books/{book}/download` | DownloadController@show | web, auth, verified, throttle:download | Updated: accepts `?format=epub` (default) or `?format=fb2` |

Note: the existing `GET /books/{book}/download` route remains; its controller is updated to accept a format query param.

---

## Classes

### New classes

| Type | Name | Namespace | Responsibility |
|------|------|-----------|----------------|
| Migration | create_book_files_table | database/migrations | Create `book_files` table |
| Migration | drop_epub_path_from_books_table | database/migrations | Drop `epub_path` from `books` |
| Migration | add_format_to_download_logs_table | database/migrations | Add `format` column to `download_logs` |
| Model | BookFile | App\Models | Eloquent model for book format files |
| Enum | BookFileFormat | App\Enums | Docx, Epub, Fb2 with `isClientAccessible()` |
| Enum | BookFileStatus | App\Enums | Pending, Processing, Ready, Failed |
| Factory | BookFileFactory | Database\Factories | Factory for BookFile model |
| Contract | FormatConverter | App\Features\Admin\Contracts | Interface for format converters |
| Exception | ConversionException | App\Features\Admin\Exceptions | Runtime exception for conversion failures |
| Service | PandocConverter | App\Features\Admin\Services\Converters | Pandoc CLI wrapper |
| Service | CalibreConverter | App\Features\Admin\Services\Converters | Calibre ebook-convert CLI wrapper |
| Service | BookConversionService | App\Features\Admin\Services | Orchestrate conversions: resolve converter, dispatch jobs, run conversion |
| Job | UploadSourceFile | App\Features\Admin\Jobs | Upload source file to S3, then trigger conversions |
| Job | ConvertBookFormat | App\Features\Admin\Jobs | Convert one format to another via converter tool |
| Controller | Admin\BookFileController | App\Features\Admin\Controllers | File upload, admin download, retry, status polling |
| Request | Admin\UploadBookFileRequest | App\Features\Admin\Requests | Validate file upload (format, file type, size) |

### Modified classes

| Type | Name | Change |
|------|------|--------|
| Model | Book | Remove `epub_path`; add `files(): HasMany`, `hasClientReadyFile(): bool` |
| Model | DownloadLog | Add `format` to `$fillable` |
| Service | DownloadService | Accept `BookFile` instead of `Book`; generate URL from `BookFile->path` with correct extension |
| Service | BookFileService | Add `deleteBookFiles(Book)` to delete all S3 files for a book; remove `deleteEpub()` |
| Service | BookAdminService | Replace epub upload logic with source file upload via `UploadSourceFile` job; update publish guard to use `hasClientReadyFile()` |
| Controller | DownloadController | Accept `?format` query param; resolve BookFile; enforce DOCX gate; pass BookFile to DownloadService |
| Policy | BookPolicy | Update `download()` to accept format param; add DOCX gate check |
| Job | ProcessBookFileUpload | **DELETE** — replaced by `UploadSourceFile` |

---

## Business Logic Rules

### Format protection (DOCX triple gate)

1. **Enum gate**: `BookFileFormat::isClientAccessible()` returns `false` for `Docx`. This is the single source of truth.
2. **Controller gate**: `DownloadController@show` checks `BookFileFormat::isClientAccessible()` and returns 403 if the requested format is not client-accessible.
3. **Service gate**: `DownloadService::generateUrl()` throws `\InvalidArgumentException` if passed a BookFile whose format is not client-accessible.

### Upload and conversion

4. When admin uploads a source file (DOCX, EPUB, or FB2), the system creates a `BookFile` record with `is_source=true` and `status=pending`, stores the file to local temp, and dispatches `UploadSourceFile`.
5. After `UploadSourceFile` completes, `BookConversionService::dispatchConversions()` reads the conversion matrix from config, creates `BookFile` records for each derivable target format (status=pending), and dispatches a `ConvertBookFormat` job for each.
6. If a `book_files` record already exists for a target format, the existing record is reused: its status is reset to `pending`, its `path` is cleared (old S3 file deleted), and a new conversion job is dispatched.
7. Re-uploading a specific derived format (e.g. a manually edited EPUB) creates/updates the `BookFile` record directly with `is_source=false`, uploads to S3, sets `status=ready`. No conversion is triggered.
8. Re-uploading a source file triggers deletion of all derived files and re-conversion (Rule 5 flow).

### Conversion execution

9. `ConvertBookFormat` downloads the source from S3 to a local temp directory, runs the converter, uploads the result to S3, and updates the `BookFile` record.
10. On conversion failure: `BookFile.status` is set to `failed`, `error_message` is populated with stderr output (truncated to 2000 chars).
11. Retry action (admin) resets a `failed` BookFile to `pending` and dispatches a new `ConvertBookFormat` job.
12. Converter preference is resolved from `config('bookshop.formats.converter_preference')` at runtime. If no preference is set, Calibre is the default.

### Publish guard

13. A book cannot be published (via `toggleStatus` or `updateBook`) unless `Book::hasClientReadyFile()` returns `true` — at least one client-accessible BookFile with `status=ready` must exist.
14. This replaces the old guard that checked `epub_path !== null`.

### Client download

15. `DownloadController@show` accepts an optional `?format=` query parameter. Valid values: `epub` (default), `fb2`. Any other value returns 422.
16. The controller resolves the BookFile for the requested format. If no `BookFile` exists for that format, or its status is not `ready`, returns 404.
17. DOCX format in the query param returns 403 (Rule 2).
18. The `format` is recorded in `download_logs.format`.

### Admin file management

19. Admin can download ANY format (including DOCX) for review via `Admin\BookFileController@download`. This route is protected by `admin` middleware only — no DOCX gate.
20. The admin "Files" status block shows all `BookFile` records for a book with: format label, status badge (color-coded), Download button (if ready), Re-upload button, Retry button (if failed).
21. Admin status polling endpoint (`GET /admin/books/{book}/files/status`) returns JSON array of all BookFile records with id, format, status, error_message, updated_at. Admin UI polls this every 3 seconds while any file has status `pending` or `processing`.

### Data integrity

22. The unique constraint `(book_id, format)` prevents duplicate files per format per book.
23. When a book is deleted, all `book_files` are cascade-deleted (FK constraint). The `BookAdminService::deleteBook()` method must also delete S3 files for all book_files before the DB delete.

### Backward compatibility

24. The `epub_path` column is dropped. All references to `epub_path` in models, services, controllers, factories, and tests must be updated.
25. Existing `ProcessBookFileUpload` job is deleted and replaced by `UploadSourceFile`.
26. The `BookFileService::deleteEpub()` method is replaced by a new `deleteBookFiles(Book $book)` method that iterates all book_files and deletes their S3 paths.

---

## Docker Changes

Add to `docker/php/Dockerfile` (in the `base` stage, after existing apt-get installs):

- `pandoc` — installed via apt-get
- `calibre` — installed via apt-get (`calibre` package provides `ebook-convert`)

Verify both are available:
- `pandoc --version`
- `ebook-convert --version`

Note: Calibre pulls in significant dependencies (~300MB). This is acceptable since conversion runs in the PHP container alongside queued jobs.

---

## S3 Path Convention

Old: `epubs/{uuid}.epub`

New: `books/{book_id}/{uuid}.{ext}`

Examples:
- `books/42/a1b2c3d4.docx` (source DOCX)
- `books/42/e5f6g7h8.epub` (derived or re-uploaded EPUB)
- `books/42/i9j0k1l2.fb2` (derived FB2)

All files stored on `s3-private` disk with `private` visibility.

---

## Implementation Sub-phases

### Phase 13.1 — Data Layer

Scope:
- Migration: `create_book_files_table`
- Migration: `drop_epub_path_from_books_table`
- Migration: `add_format_to_download_logs_table`
- Enum: `BookFileFormat` (Docx, Epub, Fb2) with `isClientAccessible()`, `label()`, `extension()`, `mimeType()`, `static clientAccessible()`
- Enum: `BookFileStatus` (Pending, Processing, Ready, Failed)
- Model: `BookFile` with relationships, casts, `isReady()`, `isSource()`, `isClientAccessible()`
- Factory: `BookFileFactory` with states: `source()`, `ready()`, `failed()`, `epub()`, `fb2()`, `docx()`
- Update `Book` model: remove `epub_path` from `$fillable`/PHPDoc, add `files(): HasMany`, `hasClientReadyFile(): bool`
- Update `BookFactory`: remove `epub_path` references
- Update `DownloadLog` model: add `format` to `$fillable`
- Tests: model relationships, enum methods, `hasClientReadyFile()` logic

### Phase 13.2 — Conversion Pipeline

Scope:
- Config additions in `config/bookshop.php`: `formats.conversion_matrix`, `formats.converter_preference`
- Contract: `FormatConverter` interface
- Exception: `ConversionException`
- Implementation: `PandocConverter` (wraps pandoc CLI)
- Implementation: `CalibreConverter` (wraps ebook-convert CLI)
- Service: `BookConversionService` — resolve converter, dispatch conversions, execute single conversion
- Job: `ConvertBookFormat` — download source from S3, convert, upload result, update status
- Docker: add `pandoc` and `calibre` to `docker/php/Dockerfile`
- Tests: unit tests for converter resolution, conversion matrix config parsing, job dispatch logic (mock converters in tests — do not require Pandoc/Calibre installed in CI)

### Phase 13.3 — Admin Backend

Scope:
- Job: `UploadSourceFile` — upload to S3, trigger conversions
- Delete: `ProcessBookFileUpload` job
- Update: `BookFileService` — remove `deleteEpub()`, add `deleteBookFiles(Book $book)`
- Update: `BookAdminService` — replace epub upload with source file upload via `UploadSourceFile`; update publish guard (Rule 13–14); update `deleteBook()` to call `deleteBookFiles()`
- Controller: `Admin\BookFileController` — store (upload), download (admin review), retry (re-dispatch), status (JSON polling)
- Request: `Admin\UploadBookFileRequest` — validate file (required, mimes, max size), optional format field for re-upload
- Update: `Admin\StoreBookRequest` and `Admin\UpdateBookRequest` — remove epub validation, add optional source_file validation
- Tests: upload flow, retry flow, publish guard with book_files, admin download of all formats including DOCX

### Phase 13.4 — Download Backend

Scope:
- Update: `DownloadService` — accept `BookFile` parameter, generate URL from `BookFile->path`, enforce DOCX service gate (Rule 3), record format in download log
- Update: `DownloadController` — accept `?format` query param, resolve BookFile, enforce DOCX controller gate (Rule 2), pass BookFile to service
- Update: `BookPolicy::download()` — unchanged authorization (user must own book), format validation delegated to controller
- Tests: download with `?format=epub` (happy path), download with `?format=fb2` (happy path), download with `?format=docx` (403), download with missing format file (404), download with non-ready file (404), download_logs records format correctly

### Phase 13.5 — Admin Frontend

Scope:
- Update: `admin/books/edit.blade.php` — replace epub upload field with "Source file" upload (accepts .docx, .epub, .fb2)
- New: "Files" block on book edit page — table showing each BookFile with: format, status badge (pending=yellow, processing=blue, ready=green, failed=red), Download button, Re-upload button, Retry button (visible on failed)
- Alpine.js polling: while any file has pending/processing status, poll `GET /admin/books/{book}/files/status` every 3 seconds and update badges
- Update: `admin/books/create.blade.php` — replace epub field with source file upload
- Error display: show `error_message` on failed files (expandable/collapsible)

### Phase 13.6 — Client Frontend

Scope:
- Update: `cabinet/library.blade.php` — show format picker per book (only ready client-accessible formats), each format is a download link with `?format=` param
- Update: `books/show.blade.php` — if user owns the book, show download buttons per available format
- Format badges: show available formats (EPUB, FB2) on book cards in library
- Graceful degradation: if only one format is available, show single download button without picker

---

## Risks / Notes

1. **Calibre Docker image size**: The `calibre` apt package pulls ~300MB of dependencies. Monitor final image size. If unacceptable, consider a multi-stage build that copies only `ebook-convert` binary and its dependencies.

2. **Conversion timeout**: Long documents may exceed the 120-second per-tool timeout or the 300-second job timeout. Monitor in production and adjust if needed. The job has 2 retries as a safety net.

3. **Temp disk space**: Conversions download source to local temp, create output file, then upload. Two large files exist simultaneously on disk. Ensure the PHP container has sufficient tmp space (at least 500MB recommended).

4. **Race condition on re-upload**: If admin re-uploads a source file while a conversion is still processing, the old conversion job may overwrite the new derived file. Mitigation: `ConvertBookFormat` checks that the source BookFile's `updated_at` has not changed since dispatch; if it has, the job aborts silently.

5. **CI testing**: Pandoc and Calibre are NOT installed in CI. All conversion tests must mock the `FormatConverter` interface. Integration tests with real converters should be marked with a `@group conversion` tag and skipped in CI.

6. **epub_path removal**: All test files that reference `epub_path` must be updated. A `grep -r epub_path` across the codebase must return zero results after Phase 13.1 is complete.

7. **Download log backward compatibility**: The `format` column defaults to `'epub'` so existing log records (if any existed) would remain valid. Since production has no data, this is purely defensive.

8. **Future: admin converter selection UI**: The config-driven `converter_preference` is designed to be overridable per-book in a future admin UI. This is explicitly deferred — the config is the only source for now.

9. **Future: additional formats (PDF, MOBI)**: Adding a new format requires: (a) new enum case in `BookFileFormat`, (b) new entries in conversion matrix config, (c) converter support check. No code changes to the pipeline itself.

10. **Publish guard timing**: When admin uploads a source and immediately tries to publish, conversion may not be complete yet. The UI should disable the publish button and show a message explaining that files are still being processed.

---

*End of multi-format books blueprint. This document is the authoritative reference for Phase 13 implementation.*
