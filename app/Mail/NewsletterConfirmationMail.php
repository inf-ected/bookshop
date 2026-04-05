<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// TODO (Phase 8): Move to App\Features\Newsletter\Mail\NewsletterConfirmationMail
// Full implementation in Phase 8. Will use: resources/views/emails/newsletter/confirm-subscription.blade.php
class NewsletterConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Подтвердите подписку на рассылку — Книжная лавка',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.newsletter.confirm-subscription',
        );
    }
}
