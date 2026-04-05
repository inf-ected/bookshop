<?php

declare(strict_types=1);

namespace App\Features\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// TODO: Apply middleware('password.confirm') to sensitive routes when implemented:
//   - DELETE /cabinet/account       — account deletion
//   - DELETE /settings/oauth/{provider} — unlinking last OAuth provider
//   - Future analytics/admin actions requiring re-auth
// Routes and views are ready; just add ->middleware('password.confirm') to the route definition.
class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password view.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('cabinet.index', absolute: false));
    }
}
