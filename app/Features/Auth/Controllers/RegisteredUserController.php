<?php

declare(strict_types=1);

namespace App\Features\Auth\Controllers;

use App\Features\Auth\Requests\RegisterRequest;
use App\Features\Newsletter\Services\NewsletterService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly NewsletterService $newsletterService) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'newsletter_consent' => (bool) $request->validated('newsletter_consent'),
        ]);

        event(new Registered($user));

        if ($user->newsletter_consent) {
            try {
                $this->newsletterService->addContact($user->email, $user->name);
            } catch (Throwable $e) {
                Log::warning('Failed to add new registrant to newsletter audience.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        session()->put('_guest_cart_session_id', session()->getId());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('cabinet.index');
    }
}
