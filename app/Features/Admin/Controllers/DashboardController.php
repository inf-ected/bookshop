<?php

declare(strict_types=1);

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => $this->dashboardService->getStats(),
        ]);
    }
}
