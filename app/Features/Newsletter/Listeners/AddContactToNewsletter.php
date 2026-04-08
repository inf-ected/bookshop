<?php

declare(strict_types=1);

namespace App\Features\Newsletter\Listeners;

use App\Features\Newsletter\Services\NewsletterService;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Throwable;

class AddContactToNewsletter
{
    public function __construct(private readonly NewsletterService $newsletterService) {}

    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->newsletter_consent) {
            return;
        }

        try {
            $this->newsletterService->addContact($user->email, $user->name);
        } catch (Throwable $e) {
            Log::warning('AddContactToNewsletter: failed to add registrant to audience.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
