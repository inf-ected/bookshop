<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Пароль изменён — Книжная лавка')
            ->line('Пароль вашего аккаунта был успешно изменён.')
            ->line('Если вы не совершали это действие — немедленно свяжитесь с нами и смените пароль.')
            ->action('Перейти в настройки', route('cabinet.settings'));
    }
}
