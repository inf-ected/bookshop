<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Overrides Laravel's built-in VerifyEmail notification with a Russian subject and custom template.
 *
 * Lives in app/Notifications/ (not in a feature slice) because it is wired to the framework:
 * User::sendEmailVerificationNotification() overrides the MustVerifyEmail contract method,
 * and Laravel's built-in SendEmailVerificationNotification listener calls that method directly
 * on the model when the Registered event fires. Moving this class into a feature slice would
 * require replacing the built-in listener with a custom one — unnecessary complexity.
 *
 * Called from:
 *   - User::sendEmailVerificationNotification() → triggered by Registered event (registration flow)
 *   - OAuthService::handleCallback() → triggered manually when OAuth user has no verified email
 */
class VerifyEmailNotification extends VerifyEmail
{
    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Подтвердите ваш email — Книжная лавка')
            ->markdown('emails.auth.verify-email', ['url' => $verificationUrl, 'user' => $notifiable]);
    }
}
