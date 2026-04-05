<?php

declare(strict_types=1);

namespace App\Features\Pages\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHours(24), function (): string {
            return $this->buildSitemap()->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        return response()
            ->view('seo.robots')
            ->header('Content-Type', 'text/plain');
    }

    public function buildSitemap(): Sitemap
    {
        $sitemap = Sitemap::create()
            ->add(
                Url::create(route('home'))
                    ->setPriority(1.0)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            )
            ->add(
                Url::create(route('books.index'))
                    ->setPriority(0.9)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            );

        Book::query()->published()->each(function (Book $book) use ($sitemap): void {
            $sitemap->add(
                Url::create(route('books.show', $book))
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setLastModificationDate($book->updated_at)
            );
        });

        $sitemap->add(
            Url::create(route('blog.index'))
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
        );

        if (Schema::hasTable('posts')) {
            Post::query()->published()->each(function (Post $post) use ($sitemap): void {
                $sitemap->add(
                    Url::create(route('blog.show', $post))
                        ->setPriority(0.6)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setLastModificationDate($post->updated_at)
                );
            });
        }

        return $sitemap;
    }
}
