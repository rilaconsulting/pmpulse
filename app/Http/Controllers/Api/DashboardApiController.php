<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyKpi;
use App\Models\SyncRun;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService
    ) {}

    /**
     * Get dashboard statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $days = min(max($days, 7), 365); // Clamp between 7 and 365

        // Get the latest KPIs
        $latestKpis = DailyKpi::query()
            ->latest('date')
            ->first();

        // Get KPI trend data
        $kpiTrend = DailyKpi::query()
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date')
            ->get();

        // Get the latest sync run
        $latestSync = SyncRun::query()
            ->latest('started_at')
            ->first();

        // Get property rollups
        $propertyRollups = $this->analyticsService->getPropertyRollups();

        return response()->json([
            'kpis' => [
                'current' => $latestKpis,
                'trend' => $kpiTrend,
            ],
            'syncStatus' => [
                'lastRun' => $latestSync,
            ],
            'propertyRollups' => $propertyRollups,
            'generatedAt' => now()->toIso8601String(),
        ]);
    }
}
