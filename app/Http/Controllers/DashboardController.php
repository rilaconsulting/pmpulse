<?php

namespace App\Http\Controllers;

use App\Models\AppfolioConnection;
use App\Models\DailyKpi;
use App\Models\SyncRun;
use App\Services\AnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService
    ) {}

    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        // Get the latest sync run
        $latestSync = SyncRun::query()
            ->latest('started_at')
            ->first();

        // Get AppFolio connection status
        $connection = AppfolioConnection::query()->first();

        // Get the latest KPIs
        $latestKpis = DailyKpi::query()
            ->latest('date')
            ->first();

        // Get KPI trend data for charts (last 30 days)
        $kpiTrend = DailyKpi::query()
            ->where('date', '>=', now()->subDays(30))
            ->orderBy('date')
            ->get();

        // Get property-level rollups
        $propertyRollups = $this->analyticsService->getPropertyRollups();

        return Inertia::render('Dashboard', [
            'syncStatus' => [
                'lastRun' => $latestSync?->toArray(),
                'connectionStatus' => $connection?->status ?? 'not_configured',
                'lastSuccessAt' => $connection?->last_success_at,
            ],
            'kpis' => [
                'current' => $latestKpis?->toArray(),
                'trend' => $kpiTrend->toArray(),
            ],
            'propertyRollups' => $propertyRollups,
        ]);
    }
}
