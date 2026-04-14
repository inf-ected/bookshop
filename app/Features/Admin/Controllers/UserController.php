<?php

declare(strict_types=1);

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Services\UserAdminService;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class UserController extends Controller
{
    public function __construct(private readonly UserAdminService $userAdminService) {}

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function show(User $user): View
    {
        $user->load(['orders.items.book', 'userBooks.book']);

        $books = Book::query()->ordered()->get(['id', 'title', 'slug']);

        return view('admin.users.show', compact('user', 'books'));
    }

    public function ban(User $user): RedirectResponse
    {
        try {
            $this->userAdminService->ban($user);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Пользователь заблокирован.');
    }

    public function unban(User $user): RedirectResponse
    {
        $this->userAdminService->unban($user);

        return redirect()->back()->with('success', 'Пользователь разблокирован.');
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $this->userAdminService->sendPasswordReset($user);

        return redirect()->back()->with('success', 'Письмо для сброса пароля отправлено.');
    }

    public function verifyEmail(User $user): RedirectResponse
    {
        $this->userAdminService->verifyEmail($user);

        return redirect()->back()->with('success', 'Email подтверждён.');
    }
}
