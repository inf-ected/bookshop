<?php

declare(strict_types=1);

namespace App\Features\Catalog\Controllers;

use App\Features\Catalog\Services\CatalogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly CatalogService $catalogService) {}

    public function index(Request $request): View
    {
        $books = $this->catalogService->listFeatured();
        $user = $request->user();
        $ownedBookIds = $user
            ? $this->catalogService->getOwnedBookIds($user)
            : collect();

        return view('home', compact('books', 'ownedBookIds'));
    }
}
