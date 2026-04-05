<?php

declare(strict_types=1);

namespace App\Features\Checkout\Controllers;

use App\Enums\OrderStatus;
use App\Features\Cart\Exceptions\EmptyCartException;
use App\Features\Checkout\Contracts\PaymentProvider;
use App\Features\Checkout\Services\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentProvider $paymentProvider,
    ) {}

    /**
     * Create a Stripe checkout session and redirect to Stripe.
     *
     * Rule 27: Order is created BEFORE Stripe redirect.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $order = $this->orderService->createFromCart($user, session()->getId());
        } catch (EmptyCartException) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Корзина пуста.']);
        }

        try {
            $session = $this->paymentProvider->createSession($order, $user);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during checkout session creation', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $order->status = OrderStatus::Failed;
            $order->save();

            return redirect()->route('cart.index')
                ->withErrors(['cart' => 'Ошибка при создании платежа. Попробуйте позже.']);
        }

        $order->stripe_session_id = $session['id'];
        $order->save();

        return redirect()->away($session['url']);
    }

    /**
     * Handle the Stripe success redirect.
     *
     * Rule 33: If order is already paid (webhook was faster), redirect to library.
     * Otherwise show polling page.
     */
    public function success(Request $request): View|RedirectResponse
    {
        $stripeSessionId = $request->query('session_id');

        if ($stripeSessionId) {
            $order = Order::query()
                ->where('stripe_session_id', $stripeSessionId)
                ->where('user_id', $request->user()->id)
                ->first();

            // Rule 33: if already paid, redirect to library
            if ($order && $order->status === OrderStatus::Paid) {
                return redirect()->to('/cabinet/library')
                    ->with('success', 'Оплата прошла успешно! Книги добавлены в вашу библиотеку.');
            }

            if ($order) {
                return view('checkout.success', ['order' => $order]);
            }
        }

        return view('checkout.success', ['order' => null]);
    }

    /**
     * Polling endpoint: return order status as JSON.
     *
     * Rule 33: Client polls this every 2 seconds for up to 30 seconds.
     */
    public function status(Request $request, Order $order): JsonResponse
    {
        // Ensure the order belongs to the authenticated user
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        return response()->json([
            'status' => $order->status->value,
            'paid' => $order->status === OrderStatus::Paid,
        ]);
    }
}
