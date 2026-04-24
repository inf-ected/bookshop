<?php

declare(strict_types=1);

namespace App\Features\Catalog\Controllers;

use App\Enums\BookStatus;
use App\Features\Catalog\Services\CatalogService;
use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct(private readonly CatalogService $catalogService) {}

    public function index(Request $request): View
    {
        $books = $this->catalogService->listPublished();
        $user = $request->user();
        $ownedBookIds = $user
            ? $this->catalogService->getOwnedBookIds($user)
            : collect();

        return view('books.index', compact('books', 'ownedBookIds'));
    }

    public function show(Request $request, Book $book): View
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        if ($book->isAdult() && ! $this->isAdultVerified($request)) {
            return view('books.age-gate', compact('book'));
        }

        $user = $request->user();
        $isOwned = $user && $this->catalogService->isOwnedByUser($book, $user);

        $readyClientFiles = collect();
        if ($isOwned) {
            $book->load('files');
            $readyClientFiles = $book->files->filter(fn ($f) => $f->isReady() && $f->isClientAccessible())->values();
        }

        return view('books.show', compact('book', 'isOwned', 'readyClientFiles'));
    }

    public function fragment(Request $request, Book $book): View
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        if ($book->isAdult() && ! $this->isAdultVerified($request)) {
            return view('books.age-gate', compact('book'));
        }

        return view('books.fragment', compact('book'));
    }

    private function isAdultVerified(Request $request): bool
    {
        if ($user = $request->user()) {
            return $user->is_adult_verified;
        }

        return session('adult_consent') === 'accepted';
    }
}
