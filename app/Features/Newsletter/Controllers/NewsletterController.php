<?php

declare(strict_types=1);

namespace App\Features\Newsletter\Controllers;

use App\Features\Newsletter\Services\NewsletterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class NewsletterController extends Controller
{
    public function __construct(private readonly NewsletterService $newsletterService) {}

    public function subscribe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $this->newsletterService->addContact($validated['email'], '');
        } catch (Throwable) {
            return redirect()->back()->withErrors([
                'email' => 'Не удалось оформить подписку. Попробуйте позже.',
            ]);
        }

        return redirect()->back()->with('newsletter_success', 'Вы успешно подписались на рассылку.');
    }
}
