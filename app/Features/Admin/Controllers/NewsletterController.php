<?php

declare(strict_types=1);

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Requests\SendNewsletterRequest;
use App\Features\Newsletter\Services\NewsletterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class NewsletterController extends Controller
{
    public function __construct(private readonly NewsletterService $newsletterService) {}

    public function index(): View
    {
        $subscriberCount = null;
        $error = null;

        try {
            $subscriberCount = $this->newsletterService->getSubscriberCount();
        } catch (Throwable $e) {
            Log::error('Admin NewsletterController::index failed to fetch contacts.', [
                'error' => $e->getMessage(),
            ]);
            $error = 'Не удалось получить данные о подписчиках. Подробности в логах.';
        }

        return view('admin.newsletter.index', compact('subscriberCount', 'error'));
    }

    public function send(SendNewsletterRequest $request): RedirectResponse
    {
        try {
            $this->newsletterService->sendBroadcast(
                $request->validated('subject'),
                $request->validated('body'),
            );
        } catch (Throwable $e) {
            Log::error('Admin NewsletterController::send failed.', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'send' => 'Ошибка при отправке рассылки. Подробности в логах.',
            ]);
        }

        return redirect()->back()->with('success', 'Рассылка успешно отправлена.');
    }
}
