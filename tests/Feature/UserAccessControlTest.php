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
use Tests\TestCase;

class UserAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function mockPrivateDisk(): void
    {
        $mock = Mockery::mock(Filesystem::class);
        $mock->shouldReceive('temporaryUrl')
            ->andReturn('https://s3.example.com/fake-signed-url');

        Storage::set('s3-private-presign', $mock);
    }

    private function makeBook(): Book
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
        $book = $this->makeBook();
        UserBook::factory()->revoked()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($user)->get(route('books.download', $book));

        $response->assertForbidden();
    }

    /**
     * Rule 81: Non-revoked user_book (revoked_at = null) allows download.
     */
    public function test_non_revoked_user_book_allows_download(): void
    {
        $this->mockPrivateDisk();

        $user = User::factory()->create();
        $book = Book::factory()->create();
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);
        BookFile::factory()->epub()->ready()->create(['book_id' => $book->id]);

        $response = $this->actingAs($user)->get(route('books.download', $book));

        $response->assertRedirect();
    }
}
