<?php

declare(strict_types=1);

namespace Tests\Feature\Blog;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_see_blog_index_with_published_posts(): void
    {
        $published = Post::factory()->published()->create(['title' => 'Опубликованная статья']);

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertSee('Опубликованная статья');
    }

    public function test_draft_post_not_visible_on_index(): void
    {
        Post::factory()->create([
            'title' => 'Черновик статьи',
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertDontSee('Черновик статьи');
    }

    public function test_guest_can_see_published_post_page(): void
    {
        $post = Post::factory()->published()->create([
            'title' => 'Видимая статья',
            'body' => '<p>Содержание статьи</p>',
        ]);

        $response = $this->get(route('blog.show', $post));

        $response->assertOk();
        $response->assertSee('Видимая статья');
    }

    public function test_draft_post_returns_404_on_show(): void
    {
        $post = Post::factory()->create([
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);

        $response = $this->get(route('blog.show', $post));

        $response->assertNotFound();
    }

    public function test_scheduled_post_not_visible_on_index(): void
    {
        Post::factory()->create([
            'title' => 'Запланированная статья',
            'status' => PostStatus::Published,
            'published_at' => now()->addDays(7),
        ]);

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertDontSee('Запланированная статья');
    }

    public function test_scheduled_post_returns_404_on_show(): void
    {
        $post = Post::factory()->create([
            'status' => PostStatus::Published,
            'published_at' => now()->addDays(7),
        ]);

        $response = $this->get(route('blog.show', $post));

        $response->assertNotFound();
    }

    public function test_blog_index_orders_posts_by_published_at_desc(): void
    {
        Post::factory()->published()->create([
            'title' => 'Старая статья',
            'published_at' => now()->subDays(10),
        ]);
        Post::factory()->published()->create([
            'title' => 'Новая статья',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $newerPos = strpos($response->content(), 'Новая статья');
        $olderPos = strpos($response->content(), 'Старая статья');
        $this->assertGreaterThan(0, $newerPos);
        $this->assertGreaterThan(0, $olderPos);
        $this->assertLessThan($olderPos, $newerPos);
    }
}
