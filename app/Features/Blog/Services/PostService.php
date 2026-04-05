<?php

declare(strict_types=1);

namespace App\Features\Blog\Services;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PostService
{
    /**
     * @return LengthAwarePaginator<int, Post>
     */
    public function listPublished(int $perPage = 10): LengthAwarePaginator
    {
        return Post::query()
            ->published()
            ->orderBy('published_at', 'desc')
            ->paginate($perPage);
    }
}
