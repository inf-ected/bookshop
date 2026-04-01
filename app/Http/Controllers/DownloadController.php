<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\User;
use App\Services\DownloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DownloadController extends Controller
{
    public function __construct(private readonly DownloadService $downloadService) {}

    public function show(Request $request, Book $book): RedirectResponse
    {
        Gate::authorize('download', $book);

        if (blank($book->epub_path)) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();

        $this->downloadService->logDownload($user, $book, (string) $request->ip());

        return redirect($this->downloadService->generateUrl($book));
    }
}
