<?php

declare(strict_types=1);

namespace App\Features\Checkout\Listeners;

use App\Features\Checkout\Events\OrderPaid;
use App\Features\Checkout\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $order->loadMissing(['user', 'items.book']);

        Log::info('SendOrderConfirmationEmail: sending to '.$order->user->email, [
            'order_id' => $order->id,
        ]);

        Mail::to($order->user->email)
            ->send(new OrderConfirmationMail($order));

        Log::info('SendOrderConfirmationEmail: sent', ['order_id' => $order->id]);
    }
}
