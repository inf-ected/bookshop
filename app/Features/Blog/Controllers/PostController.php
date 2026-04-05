<?php

declare(strict_types=1);

namespace App\Features\Blog\Controllers;

use App\Features\Blog\Services\PostService;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(private readonly PostService $postService) {}

    public function index(): View
    {
        $posts = $this->postService->listPublished();

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
