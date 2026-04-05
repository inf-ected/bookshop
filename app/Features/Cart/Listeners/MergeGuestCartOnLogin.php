<?php

declare(strict_types=1);

namespace App\Features\Cart\Listeners;

use App\Features\Cart\Services\CartService;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class MergeGuestCartOnLogin
{
    public function __construct(private readonly CartService $cartService) {}

    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        // Auth::login() internally calls session()->migrate(true) which regenerates
        // the session ID before firing this event. The guest cart is stored under
        // the pre-login session ID, which we save to the session just before login.
        $sessionId = session()->pull('_guest_cart_session_id', session()->getId());

        $this->cartService->mergeGuestCart($user, $sessionId);
    }
}
