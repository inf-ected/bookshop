<?php

declare(strict_types=1);

namespace App\Features\Checkout\Controllers;

use App\Enums\OrderStatus;
use App\Features\Cart\Exceptions\EmptyCartException;
use App\Features\Checkout\Exceptions\PaymentException;
use App\Features\Checkout\Services\OrderService;
use App\Features\Checkout\Services\PaymentProviderRegistry;
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
        private readonly PaymentProviderRegistry $registry,
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
        $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', $this->registry->availableSlugs())],
        ]);

        $paymentProvider = $this->registry->get($request->input('provider'));
        $user = $request->user();

        try {
            $order = $this->orderService->createFromCart($user, session()->getId());
        } catch (EmptyCartException) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Корзина пуста.']);
        }

        try {
            $session = $paymentProvider->createSession($order, $user);
        } catch (PaymentException $e) {
            Log::error('Payment provider error during session creation', [
                'provider' => $paymentProvider->getName(),
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $order->status = OrderStatus::Failed;
            $order->save();

            return redirect()->route('cart.index')
                ->withErrors(['cart' => 'Ошибка при создании платежа. Попробуйте позже.']);
        }

        // Store provider name in session so success() can resolve the correct provider instance.
        session(['payment_provider' => $paymentProvider->getName()]);

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
        $providerSlug = session('payment_provider');

        if ($providerSlug === null || ! $this->registry->isEnabled($providerSlug)) {
            return view('checkout.success', ['order' => null]);
        }

        $paymentProvider = $this->registry->get($providerSlug);
        $sessionId = $paymentProvider->extractReturnSessionId($request);

        if ($sessionId) {
            $order = $this->orderService->findByProviderSession($providerSlug, $sessionId, $request->user()->id);

            if ($order) {
                try {
                    $paymentProvider->handleReturn($request, $order);
                } catch (PaymentException $e) {
                    Log::error('Payment provider error on return redirect', [
                        'provider' => $providerSlug,
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }

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
