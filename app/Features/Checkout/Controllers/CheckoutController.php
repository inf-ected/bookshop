<?php

declare(strict_types=1);

namespace App\Features\Checkout\Controllers;

use App\Enums\OrderStatus;
use App\Features\Cart\Exceptions\EmptyCartException;
use App\Features\Checkout\Contracts\PaymentProvider;
use App\Features\Checkout\Exceptions\PaymentException;
use App\Features\Checkout\Services\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentProvider $paymentProvider,
    ) {}

    /**
     * Create a payment session and redirect the user to the provider.
     *
     * Rule 27: Order is created BEFORE provider redirect.
     *
     * @throws Throwable
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
        } catch (PaymentException $e) {
            Log::error('Payment provider error during session creation', [
                'provider' => $this->paymentProvider->getName(),
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $order->status = OrderStatus::Failed;
            $order->save();

            return redirect()->route('cart.index')
                ->withErrors(['cart' => 'Ошибка при создании платежа. Попробуйте позже.']);
        }

        // Store provider name in session so success() can resolve the correct transaction
        session(['payment_provider' => $this->paymentProvider->getName()]);

        return redirect()->away($session['url']);
    }

    /**
     * Handle the provider's success redirect.
     *
     * Rule 33: If order is already paid (webhook was faster), redirect to library.
     * Otherwise show polling page.
     */
    public function success(Request $request): View|RedirectResponse
    {
        $provider = session('payment_provider', $this->paymentProvider->getName());

        // extractReturnSessionId() is intentionally delegated to the injected singleton.
        // The session value above is used only to scope the transaction lookup by provider name.
        // When a second provider (e.g. PayPal) is added, this controller will need to resolve
        // the correct provider instance by name before calling extractReturnSessionId/handleReturn.
        $sessionId = $this->paymentProvider->extractReturnSessionId($request);

        if ($sessionId) {
            $order = $this->orderService->findByProviderSession($provider, $sessionId, $request->user()->id);

            if ($order) {
                // Allow provider to perform any post-redirect action (e.g. PayPal capture)
                try {
                    $this->paymentProvider->handleReturn($request, $order);
                } catch (PaymentException $e) {
                    Log::error('Payment provider error on return redirect', [
                        'provider' => $provider,
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Rule 33: if already paid, redirect to library
                if ($order->status === OrderStatus::Paid) {
                    return redirect()->route('cabinet.library')
                        ->with('success', 'Оплата прошла успешно! Книги добавлены в вашу библиотеку.');
                }

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
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        return response()->json([
            'status' => $order->status->value,
            'paid' => $order->status === OrderStatus::Paid,
        ]);
    }
}
