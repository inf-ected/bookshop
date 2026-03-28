<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OauthProvider;
use App\Models\OAuthProvider as OAuthProviderModel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class OAuthController extends Controller
{
    public function redirect(string $provider): RedirectResponse
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
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['oauth' => 'Не удалось войти через внешний сервис.']);
        }

        $oauthRecord = OAuthProviderModel::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($oauthRecord) {
            Auth::login($oauthRecord->user);

            return redirect()->intended('/cabinet');
        }

        $email = $socialUser->getEmail();

        if ($email) {
            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $email,
                    'email' => $email,
                    'password' => null,
                    'email_verified_at' => now(),
                ]);
            }

            OAuthProviderModel::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ]);

            Auth::login($user);

            return redirect('/cabinet');
        }

        session()->put('oauth_pending', [
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'name' => $socialUser->getName(),
        ]);

        return redirect()->route('auth.complete-registration');
    }

    public function showCompleteRegistration(): RedirectResponse|View
    {
        if (! session()->has('oauth_pending')) {
            return redirect()->route('login');
        }

        return view('auth.complete-registration');
    }

    public function completeRegistration(Request $request): RedirectResponse
    {
        if (! session()->has('oauth_pending')) {
            return redirect()->route('login');
        }

        $pending = session()->get('oauth_pending');

        $existingUser = User::query()->where('email', $request->input('email'))->first();

        $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $existingUser ? '' : 'unique:users',
            ],
        ]);

        if ($existingUser) {
            OAuthProviderModel::create([
                'user_id' => $existingUser->id,
                'provider' => $pending['provider'],
                'provider_id' => $pending['provider_id'],
                'token' => $pending['token'],
                'refresh_token' => $pending['refresh_token'],
            ]);

            Auth::login($existingUser);
            session()->forget('oauth_pending');

            return redirect('/cabinet');
        }

        $user = User::create([
            'name' => $pending['name'] ?? $request->validated('email'),
            'email' => $request->input('email'),
            'password' => null,
            'email_verified_at' => null,
        ]);

        OAuthProviderModel::create([
            'user_id' => $user->id,
            'provider' => $pending['provider'],
            'provider_id' => $pending['provider_id'],
            'token' => $pending['token'],
            'refresh_token' => $pending['refresh_token'],
        ]);

        $user->sendEmailVerificationNotification();

        Auth::login($user);
        session()->forget('oauth_pending');

        return redirect('/email/verify');
    }
}
