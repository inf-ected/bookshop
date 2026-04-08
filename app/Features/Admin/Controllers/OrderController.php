<?php

declare(strict_types=1);

namespace App\Features\Admin\Controllers;

use App\Enums\OrderStatus;
use App\Features\Admin\Services\OrderAdminService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class OrderController extends Controller
{
    public function __construct(private readonly OrderAdminService $orderAdminService) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->value();

        $orderStatus = $status !== '' ? OrderStatus::tryFrom($status) : null;

        $orders = Order::query()
            ->with('user')
            ->when($orderStatus !== null, fn ($q) => $q->where('status', $orderStatus))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $statuses = OrderStatus::cases();

        return view('admin.orders.index', compact('orders', 'statuses', 'status'));
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items.book']);

        return view('admin.orders.show', compact('order'));
    }

    /**
     * @throws Throwable
     */
    public function refund(Order $order): RedirectResponse
    {
        try {
            $this->orderAdminService->refund($order);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Заказ возвращён.');
    }
}
