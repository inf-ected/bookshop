<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $order->loadMissing(['user', 'items.book']);

        Mail::to($order->user->email)
            ->queue(new OrderConfirmationMail($order));
    }
}
