<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class DownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    private function downloadUrl(Book $book): string
    {
        return route('books.download', $book);
    }

    /**
     * Swap the s3-private-presign disk with a mock that returns a predictable temporaryUrl.
     */
    private function mockPrivateDisk(): void
    {
        $mock = Mockery::mock(Filesystem::class);
        $mock->shouldReceive('temporaryUrl')
            ->andReturn('https://s3.example.com/fake-signed-url');

        Storage::set('s3-private-presign', $mock);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $book = Book::factory()->create();

        $response = $this->get($this->downloadUrl($book));

        $response->assertRedirectToRoute('login');
    }

    public function test_non_owner_gets_403(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        // No UserBook record — user does not own the book

        $response = $this->actingAs($user)->get($this->downloadUrl($book));

        $response->assertForbidden();
    }

    /**
     * Full download happy path requires Phase 13.4 DownloadService update
     * (BookFile-based URL generation instead of epub_path).
     */
    #[Group('phase-13-4')]
    public function test_owner_can_download_book(): void
    {
        $this->markTestSkipped('Requires Phase 13.4: DownloadService BookFile-based implementation.');
    }

    public function test_book_without_ready_file_returns_404(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $response = $this->actingAs($user)->get($this->downloadUrl($book));

        $response->assertNotFound();
    }

    /**
     * Rate limiting test requires a ready BookFile and a working download flow.
     * Full download flow is completed in Phase 13.4 (DownloadService updated).
     * This test verifies the throttle fires after 10 requests once the service
     * is updated; for now it documents the expected behaviour.
     */
    #[Group('phase-13-4')]
    public function test_rate_limit_returns_429_after_10_requests(): void
    {
        $this->mockPrivateDisk();

        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        // Clear any cached rate limiter state
        RateLimiter::clear('download:'.$user->id.':'.$book->id);

        // Without a ready BookFile, all requests return 404 — the throttle is still applied
        // at the route level and fires after 10 attempts regardless of response status.
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->get($this->downloadUrl($book));
        }

        $response = $this->actingAs($user)->get($this->downloadUrl($book));
        $response->assertStatus(429);
    }
}
