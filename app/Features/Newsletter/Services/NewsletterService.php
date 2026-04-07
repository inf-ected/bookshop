<?php

declare(strict_types=1);

namespace App\Features\Newsletter\Services;

use Illuminate\Support\Facades\Log;
use Resend\Client;
use Throwable;

class NewsletterService
{
    public function __construct(private readonly Client $resend) {}

    /**
     * Add a contact to the configured Resend audience.
     *
     * Silently logs a warning and returns early if audience_id is not configured.
     */
    public function addContact(string $email, string $firstName): void
    {
        $audienceId = config('services.resend.audience_id');

        if (empty($audienceId)) {
            Log::warning('NewsletterService::addContact called but RESEND_AUDIENCE_ID is not configured.');

            return;
        }

        try {
            $this->resend->contacts->create([
                'audience_id' => $audienceId,
                'email' => $email,
                'first_name' => $firstName,
                'unsubscribed' => false,
            ]);
        } catch (Throwable $e) {
            Log::error('NewsletterService::addContact failed.', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create and immediately send a broadcast to the configured Resend audience.
     */
    public function sendBroadcast(string $subject, string $html): void
    {
        $audienceId = config('services.resend.audience_id');

        if (empty($audienceId)) {
            Log::warning('NewsletterService::sendBroadcast called but RESEND_AUDIENCE_ID is not configured.');

            return;
        }

        try {
            $broadcast = $this->resend->broadcasts->create([
                'audience_id' => $audienceId,
                'from' => config('mail.from.address'),
                'subject' => $subject,
                'html' => $html,
            ]);

            $this->resend->broadcasts->send($broadcast->getAttribute('id'), []);
        } catch (Throwable $e) {
            Log::error('NewsletterService::sendBroadcast failed.', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the number of contacts in the configured Resend audience.
     * Returns null if audience is not configured or API call fails.
     */
    public function getSubscriberCount(): ?int
    {
        $audienceId = config('services.resend.audience_id');

        if (empty($audienceId)) {
            return null;
        }

        $contacts = $this->resend->contacts->list(['audience_id' => $audienceId]);

        return count($contacts->data ?? []);
    }
}
