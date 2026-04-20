<?php

declare(strict_types=1);

namespace App\Features\Cart\Controllers;

use App\Features\Cart\Services\CartService;
use App\Features\Checkout\Services\PaymentProviderRegistry;
use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly PaymentProviderRegistry $paymentRegistry,
    ) {}

    public function index(): View
    {
        $user = Auth::user();
        $sessionId = session()->getId();

        $items = $this->cartService->getItems($user, $sessionId);
        $total = $this->cartService->getTotalFromItems($items);
        $paymentProviders = $this->paymentRegistry->availableSlugs();

        return view('cart.index', compact('items', 'total', 'paymentProviders'));
    }

    public function store(Book $book): RedirectResponse
    {
        $user = Auth::user();
        $sessionId = session()->getId();

        try {
            $this->cartService->addItem($book, $user, $sessionId);
        } catch (RuntimeException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return back()->with('success', 'Книга добавлена в корзину.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        $user = Auth::user();
        $sessionId = session()->getId();

        $this->cartService->removeItem($book, $user, $sessionId);

        return back()->with('success', 'Книга удалена из корзины.');
    }
}
