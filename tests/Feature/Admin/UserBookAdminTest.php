<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBookAdminTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Revoke
    // -------------------------------------------------------------------------

    public function test_admin_can_revoke_user_book(): void
    {
        $admin = User::factory()->admin()->create();
        $userBook = UserBook::factory()->create();

        $this->assertNull($userBook->revoked_at);

        $this->actingAs($admin)
            ->patch("/admin/user-books/{$userBook->id}/revoke")
            ->assertRedirect();

        $this->assertNotNull($userBook->fresh()->revoked_at);
    }

    public function test_non_admin_cannot_revoke_user_book(): void
    {
        $user = User::factory()->create();
        $userBook = UserBook::factory()->create();

        $this->actingAs($user)
            ->patch("/admin/user-books/{$userBook->id}/revoke")
            ->assertStatus(404);

        $this->assertNull($userBook->fresh()->revoked_at);
    }

    // -------------------------------------------------------------------------
    // Restore
    // -------------------------------------------------------------------------

    public function test_admin_can_restore_revoked_user_book(): void
    {
        $admin = User::factory()->admin()->create();
        $userBook = UserBook::factory()->revoked()->create();

        $this->assertNotNull($userBook->revoked_at);

        $this->actingAs($admin)
            ->patch("/admin/user-books/{$userBook->id}/restore")
            ->assertRedirect();

        $this->assertNull($userBook->fresh()->revoked_at);
    }

    // -------------------------------------------------------------------------
    // Grant
    // -------------------------------------------------------------------------

    public function test_admin_can_grant_book_to_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/grant-book", [
                'book_slug' => $book->slug,
                'reason' => 'Тестовая причина',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'order_id' => null,
        ]);
    }

    public function test_grant_creates_user_book_with_null_order_id(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/grant-book", [
                'book_slug' => $book->slug,
            ]);

        $userBook = UserBook::query()
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->firstOrFail();

        $this->assertNull($userBook->order_id);
        $this->assertNotNull($userBook->granted_at);
    }

    public function test_grant_requires_valid_book_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/grant-book", [
                'book_slug' => 'non-existent-slug',
            ])
            ->assertSessionHasErrors('book_slug');
    }

    public function test_non_admin_cannot_grant_book(): void
    {
        $regularUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($regularUser)
            ->post("/admin/users/{$targetUser->id}/grant-book", [
                'book_id' => $book->id,
            ])
            ->assertStatus(404);
    }
}
