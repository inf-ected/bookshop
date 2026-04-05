<?php

declare(strict_types=1);

namespace App\Features\Pages\Observers;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

class PostObserver
{
    public function created(Post $post): void
    {
        if ($post->status === PostStatus::Published) {
            Cache::forget('sitemap.xml');
        }
    }

    public function updated(Post $post): void
    {
        if ($post->wasChanged('status', 'slug')) {
            Cache::forget('sitemap.xml');
        }
    }

    public function deleted(Post $post): void
    {
        Cache::forget('sitemap.xml');
    }
}
