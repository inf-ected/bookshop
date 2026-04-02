<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CabinetController extends Controller
{
    /**
     * Redirect to library (Rule 42).
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('cabinet.library');
    }

    /**
     * Show owned books (Rule 43).
     */
    public function library(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $userBooks = $user->userBooks()
            ->with('book')
            ->latest()
            ->get();

        return view('cabinet.library', compact('userBooks'));
    }

    /**
     * Show order history paginated 10/page, created_at DESC (Rule 44).
     */
    public function orders(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $orders = $user->orders()
            ->with('items.book')
            ->latest()
            ->paginate(10);

        return view('cabinet.orders', compact('orders'));
    }
}
