<?php

declare(strict_types=1);

namespace App\Features\Blog\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(): View
    {
        /** @var LengthAwarePaginator<int, Post> $posts */
        $posts = Post::query()
            ->published()
            ->orderBy('published_at', 'desc')
            ->paginate(10);

        return view('blog.index', compact('posts'));
    }

    public function show(Post $post): View
    {
        if (! $post->isPublished()) {
            abort(404);
        }

        return view('blog.show', compact('post'));
    }
}
