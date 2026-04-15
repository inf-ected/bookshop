<?php

declare(strict_types=1);

namespace App\Features\Catalog\Controllers;

use App\Features\Blog\Services\PostService;
use App\Features\Catalog\Services\CatalogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService,
        private readonly PostService $postService,
    ) {}

    public function index(Request $request): View
    {
        $books = $this->catalogService->listFeatured();
        $user = $request->user();
        $ownedBookIds = $user
            ? $this->catalogService->getOwnedBookIds($user)
            : collect();

        $posts = $this->postService->listPublished(3);

        return view('home', compact('books', 'ownedBookIds', 'posts'));
    }
}
