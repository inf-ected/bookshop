<?php

declare(strict_types=1);

namespace App\Features\Admin\Services;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;

class UserAdminService
{
    /**
     * Ban a user by setting banned_at to now.
     * Rule 77: admins cannot be banned.
     *
     * @throws InvalidArgumentException
     */
    public function ban(User $user): void
    {
        if ($user->isAdmin()) {
            throw new InvalidArgumentException('Нельзя заблокировать администратора.');
        }

        $user->banned_at = now();
        $user->save();
    }

    /**
     * Unban a user by clearing banned_at.
     */
    public function unban(User $user): void
    {
        $user->banned_at = null;
        $user->save();
    }

    /**
     * Send a password reset link to the user's email.
     * Rule 78: uses Password::sendResetLink.
     */
    public function sendPasswordReset(User $user): void
    {
        Password::sendResetLink(['email' => $user->email]);
    }

    /**
     * Mark the user's email as verified.
     * Rule 79: fires Verified event only if not already verified.
     */
    public function verifyEmail(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->markEmailAsVerified();

        event(new Verified($user));
    }
}
