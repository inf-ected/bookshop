<?php

declare(strict_types=1);

namespace App\Features\Checkout\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Features\Cart\Exceptions\EmptyCartException;
use App\Features\Checkout\Exceptions\PaymentException;
use App\Features\Checkout\Services\OrderService;
use App\Features\Checkout\Services\PaymentProviderRegistry;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
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

        $paymentProvider = $this->registry->get(PaymentGateway::from($request->input('provider')));
        $user = $request->user();

        try {
            $order = $this->orderService->createFromCart($user, session()->getId());
        } catch (EmptyCartException) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Корзина пуста.']);
        }

        try {
            $session = $paymentProvider->createSession($order, $user);
        } catch (PaymentException|ConnectionException $e) {
            Log::error('Payment provider error during session creation', [
                'provider' => $paymentProvider->getName()->value,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $order->status = OrderStatus::Failed;
            $order->save();

            return redirect()->route('cart.index')
                ->withErrors(['cart' => 'Ошибка при создании платежа. Попробуйте позже.']);
        }

        return redirect()->away($session['url']);
    }

    /**
     * Handle the provider's success redirect.
     *
     * Provider is detected from the return URL query params — each provider uses a
     * distinct parameter (Stripe: session_id, PayPal: token). This avoids session-based
     * provider tracking, which breaks when a user has multiple checkout tabs open and
     * the session key gets overwritten by the later provider.
     *
     * Rule 33: If order is already paid (webhook was faster), redirect to library.
     * Otherwise, show polling page.
     */
    public function success(Request $request): View|RedirectResponse
    {
        $gateway = PaymentGateway::tryFrom((string) $request->query('provider', ''));

        if ($gateway === null || ! $this->registry->isEnabled($gateway)) {
            return view('checkout.success', ['order' => null]);
        }

        $provider = $this->registry->get($gateway);
        $sessionId = $provider->extractReturnSessionId($request);

        if ($sessionId === null) {
            return view('checkout.success', ['order' => null]);
        }

        $order = $this->orderService->findByProviderSession($gateway->value, $sessionId, $request->user()->id);

        if ($order === null) {
            return view('checkout.success', ['order' => null]);
        }

        try {
            $provider->handleReturn($request, $order);
        } catch (PaymentException|ConnectionException $e) {
            Log::error('Payment provider error on return redirect', [
                'provider' => $gateway->value,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        $order->refresh();

        if ($order->status === OrderStatus::Paid) {
            return redirect()->route('cabinet.library')
                ->with('success', 'Оплата прошла успешно! Книги добавлены в вашу библиотеку.');
        }

        return view('checkout.success', ['order' => $order]);
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
