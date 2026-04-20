<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookFile;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class Phase11DataLayerTest extends TestCase
{
    use RefreshDatabase;

    private function mockPrivateDisk(): void
    {
        $mock = Mockery::mock(Filesystem::class);
        $mock->shouldReceive('temporaryUrl')
            ->andReturn('https://s3.example.com/fake-signed-url');

        Storage::set('s3-private-presign', $mock);
    }

    private function makeBookWithEpub(): Book
    {
        return Book::factory()->create();
    }

    /**
     * Rule 76: CheckNotBanned middleware aborts 403 if banned_at is set.
     */
    public function test_banned_user_gets_403_on_authenticated_web_route(): void
    {
        $user = User::factory()->banned()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertForbidden();
    }

    /**
     * Rule 76: Non-banned user (banned_at = null) can access normally.
     */
    public function test_non_banned_user_can_access_web_route(): void
    {
        $user = User::factory()->create(['banned_at' => null]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
    }

    /**
     * Rule 81: Revoked user_book (revoked_at set) blocks the download endpoint.
     */
    public function test_revoked_user_book_blocks_download(): void
    {
        $user = User::factory()->create();
        $book = $this->makeBookWithEpub();
        UserBook::factory()->revoked()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($user)->get(route('books.download', $book));

        $response->assertForbidden();
    }

    /**
     * Rule 81: Non-revoked user_book (revoked_at = null) allows download.
     * A ready EPUB BookFile must exist for the download to succeed.
     * Full redirect requires Phase 13.4 DownloadService BookFile-based implementation.
     */
    #[Group('phase-13-4')]
    public function test_non_revoked_user_book_allows_download(): void
    {
        $this->markTestSkipped('Requires Phase 13.4: DownloadService BookFile-based implementation.');
    }

}
