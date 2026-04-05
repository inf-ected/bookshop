<?php

declare(strict_types=1);

namespace App\Features\Pages\Services;

use App\Models\Book;
use App\Models\Post;
use Illuminate\Support\Facades\Schema;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapService
{
    public function build(): Sitemap
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
            $url = Url::create(route('books.show', $book))
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setLastModificationDate($book->updated_at);

            if ($book->cover_url) {
                $url->addImage($book->cover_url, $book->title);
            }

            $sitemap->add($url);
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
