<?php

declare(strict_types=1);

namespace App\Features\Cabinet\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CabinetController extends Controller
{
    /**
     * Redirect to library, or to admin dashboard for admins (Rule 42).
     */
    public function index(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('cabinet.library');
    }

    /**
     * Show owned books (Rule 43). Admins are redirected — they cannot own books.
     */
    public function library(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('cabinet.settings');
        }

        $userBooks = $user->userBooks()
            ->with('book')
            ->latest()
            ->get();

        return view('cabinet.library', compact('userBooks'));
    }

    /**
     * Show order history paginated 10/page, created_at DESC (Rule 44).
     * Admins are redirected — they do not place orders.
     */
    public function orders(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('cabinet.settings');
        }

        $orders = $user->orders()
            ->with('items.book')
            ->latest()
            ->paginate(10);

        return view('cabinet.orders', compact('orders'));
    }
}
