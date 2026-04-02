<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OauthProvider;
use App\Http\Requests\Auth\CompleteRegistrationRequest;
use App\Models\User as UserModel;
use App\Services\OAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OAuthController extends Controller
{
    public function __construct(private readonly OAuthService $oauthService) {}

    public function redirect(string $provider): Response
    {
        if (! OauthProvider::tryFrom($provider)) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        if (! OauthProvider::tryFrom($provider)) {
            abort(404);
        }

        try {
            /** @var User $socialUser */
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['oauth' => 'Не удалось войти через внешний сервис.']);
        }

        // Handle OAuth provider linking for authenticated users (Rule C1).
        if (session()->pull('oauth_link_intent')) {
            if (! Auth::check()) {
                return redirect()->route('login');
            }

            /** @var UserModel $authUser */
            $authUser = Auth::user();

            try {
                $this->oauthService->linkProvider($authUser, $provider, $socialUser);
            } catch (\RuntimeException $e) {
                return redirect()->route('cabinet.settings')
                    ->withErrors(['provider' => $e->getMessage()]);
            }

            return redirect()->route('cabinet.settings')->with('status', 'provider-linked');
        }

        $result = $this->oauthService->handleCallback($provider, $socialUser);

        if ($result['action'] === 'needs_email') {
            session()->put('oauth_pending', $result['pendingData']);

            return redirect()->route('auth.complete-registration');
        }

        if (! $result['user']) {
            abort(500);
        }

        Auth::login($result['user']);
        session()->regenerate();

        return redirect()->intended(route('cabinet.index'));
    }

    public function showCompleteRegistration(): RedirectResponse|View
    {
        if (! session()->has('oauth_pending')) {
            return redirect()->route('login');
        }

        return view('auth.complete-registration');
    }

    public function completeRegistration(CompleteRegistrationRequest $request): RedirectResponse
    {
        if (! session()->has('oauth_pending')) {
            return redirect()->route('login');
        }

        $pending = session()->get('oauth_pending');

        $user = $this->oauthService->completeRegistration($request->validated('email'), $pending);

        Auth::login($user);
        session()->regenerate();
        session()->forget('oauth_pending');

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->route('cabinet.index');
    }
}
