<?php

declare(strict_types=1);

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Requests\GrantBookRequest;
use App\Features\Admin\Services\UserBookAdminService;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class UserBookController extends Controller
{
    public function __construct(private readonly UserBookAdminService $userBookAdminService) {}

    public function revoke(UserBook $userBook): RedirectResponse
    {
        $this->userBookAdminService->revoke($userBook);

        return redirect()->back()->with('success', 'Доступ к книге отозван.');
    }

    public function restore(UserBook $userBook): RedirectResponse
    {
        $this->userBookAdminService->restore($userBook);

        return redirect()->back()->with('success', 'Доступ к книге восстановлен.');
    }

    public function grant(GrantBookRequest $request, User $user): RedirectResponse
    {
        $book = Book::query()->where('slug', $request->validated('book_slug'))->firstOrFail();

        try {
            $this->userBookAdminService->grant($user, $book, $request->validated('reason'));
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Книга выдана пользователю.');
    }
}
