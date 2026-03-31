<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Order;
use App\Models\User;

interface PaymentProvider
{
    /**
     * Create a payment session for the given order and user.
     *
     * @return array{id: string, url: string}
     */
    public function createSession(Order $order, User $user): array;
}
