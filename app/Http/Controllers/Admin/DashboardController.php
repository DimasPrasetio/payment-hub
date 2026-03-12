<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\DashboardService;
use Illuminate\Contracts\View\View;

class DashboardController extends AdminController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function index(): View
    {
        return $this->renderPage('admin.dashboard.index', [
            'title' => 'Dashboard',
            'heading' => 'Payment Hub Dashboard',
            'kicker' => 'Control Center',
            'description' => 'Ringkasan operasional pembayaran, provider, event, dan webhook dari data aktual database.',
        ], $this->dashboardService->build());
    }
}
