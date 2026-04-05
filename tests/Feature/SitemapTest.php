<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('sitemap.xml');
    }

    public function test_sitemap_returns_200_with_xml_content_type(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
    }

    public function test_published_book_appears_in_sitemap(): void
    {
        $book = Book::factory()->published()->create(['slug' => 'my-published-book']);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertSee('my-published-book', escape: false);
    }

    public function test_draft_book_does_not_appear_in_sitemap(): void
    {
        $book = Book::factory()->create(['slug' => 'my-draft-book']);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertDontSee('my-draft-book', escape: false);
    }

    public function test_published_post_appears_in_sitemap(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'my-published-post']);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertSee('my-published-post', escape: false);
    }

    public function test_draft_post_does_not_appear_in_sitemap(): void
    {
        Post::factory()->create(['slug' => 'my-draft-post']);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertDontSee('my-draft-post', escape: false);
    }

    public function test_robots_txt_returns_200_with_plain_text(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Disallow: /admin', escape: false);
        $response->assertSee('Disallow: /cabinet', escape: false);
        $response->assertSee('Disallow: /checkout', escape: false);
        $response->assertSee('Disallow: /cart', escape: false);
        $response->assertSee('Sitemap:', escape: false);
    }
}
