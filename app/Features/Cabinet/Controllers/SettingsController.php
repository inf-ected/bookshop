<?php

declare(strict_types=1);

namespace App\Features\Cabinet\Controllers;

use App\Enums\OauthProvider;
use App\Features\Auth\Services\OAuthService;
use App\Features\Cabinet\Notifications\PasswordChangedNotification;
use App\Features\Cabinet\Requests\UpdatePasswordRequest;
use App\Features\Cabinet\Requests\UpdateProfileRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly OAuthService $oauthService) {}

    /**
     * Show profile and settings page.
     */
    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $linkedProviders = $user->oauthProviders()
            ->pluck('provider')
            ->map(fn (OauthProvider $p) => $p->value)
            ->all();

        return view('cabinet.settings', compact('user', 'linkedProviders'));
    }

    /**
     * Update profile name only. Email is read-only (Rule 46).
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['name' => $request->validated('name')]);

        return redirect()->route('cabinet.settings')->with('status', 'profile-updated');
    }

    /**
     * Update password. Only allowed if user currently has a password (Rule 47).
     * The authorize() check in UpdatePasswordRequest guards against OAuth-only users.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['password' => Hash::make($request->validated('password'))]);
        $user->notify(new PasswordChangedNotification);

        return redirect()->route('cabinet.settings')->with('status', 'password-updated');
    }

    /**
     * Link an OAuth provider to the current user's account.
     * Stores a link intent in session then redirects to the OAuth provider.
     */
    public function linkProvider(Request $request, string $provider): Response
    {
        if (! OauthProvider::tryFrom($provider)) {
            abort(404);
        }

        session()->put('oauth_link_intent', true);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Unlink an OAuth provider from the current user's account (Rule 45).
     */
    public function unlinkProvider(Request $request, string $provider): RedirectResponse
    {
        if (! OauthProvider::tryFrom($provider)) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $this->oauthService->unlinkProvider($user, $provider);
        } catch (\RuntimeException $e) {
            return redirect()->route('cabinet.settings')
                ->withErrors(['provider' => $e->getMessage()]);
        }

        return redirect()->route('cabinet.settings')->with('status', 'provider-unlinked');
    }
}
