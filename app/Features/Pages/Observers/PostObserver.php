<?php

declare(strict_types=1);

namespace App\Features\Pages\Observers;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;

class PostObserver
{
    public function updated(Post $post): void
    {
        if ($post->wasChanged('status')) {
            Cache::forget('sitemap.xml');
        }
    }
}
