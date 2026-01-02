<?php

namespace App\Http\Controllers;

use App\Models\DailyKpi;
use App\Models\SyncRun;
use App\Services\AnalyticsService;
use App\Services\AppfolioClient;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly AppfolioClient $appfolioClient
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

        // Get sync health data for the widget
        $syncHealth = $this->getSyncHealthData($latestSync);

        return Inertia::render('Dashboard', [
            'syncStatus' => [
                'lastRun' => $latestSync?->toArray(),
                'connectionStatus' => $this->appfolioClient->getStatus(),
                'lastSuccessAt' => $this->appfolioClient->getLastSuccessAt(),
            ],
            'syncHealth' => $syncHealth,
            'kpis' => [
                'current' => $latestKpis?->toArray(),
                'trend' => $kpiTrend->toArray(),
            ],
            'propertyRollups' => $propertyRollups,
        ]);
    }

    /**
     * Get sync health data for the dashboard widget.
     */
    private function getSyncHealthData(?SyncRun $lastRun): array
    {
        $days = 7;
        $periodStart = now()->subDays($days)->startOfDay();

        // Get last successful run
        $lastSuccess = SyncRun::query()
            ->where('status', 'completed')
            ->latest('started_at')
            ->first();

        // Get sync stats for the period
        $stats = SyncRun::query()
            ->where('started_at', '>=', $periodStart)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalRuns = array_sum($stats);
        $successCount = $stats['completed'] ?? 0;
        $failureCount = $stats['failed'] ?? 0;

        // Calculate success rate
        $successRate = $totalRuns > 0
            ? round(($successCount / $totalRuns) * 100, 1)
            : null;

        // Get daily sync counts for chart
        $dailyStats = SyncRun::query()
            ->where('started_at', '>=', $periodStart)
            ->selectRaw('DATE(started_at) as date, status, COUNT(*) as count, SUM(resources_synced) as total_synced')
            ->groupBy(DB::raw('DATE(started_at)'), 'status')
            ->orderBy('date')
            ->get();

        // Transform daily stats into chart-friendly format
        $chartData = [];
        foreach ($dailyStats as $stat) {
            $date = $stat->date;
            if (! isset($chartData[$date])) {
                $chartData[$date] = [
                    'date' => $date,
                    'completed' => 0,
                    'failed' => 0,
                    'total_synced' => 0,
                ];
            }
            $chartData[$date][$stat->status] = $stat->count;
            if ($stat->status === 'completed') {
                $chartData[$date]['total_synced'] = $stat->total_synced;
            }
        }

        // Get aggregate resource metrics from last run
        $resourceTotals = $lastRun ? $lastRun->getResourceMetrics() : [];

        // Get recent errors
        $recentErrors = [];
        $runsWithErrors = SyncRun::query()
            ->where('started_at', '>=', $periodStart)
            ->where('errors_count', '>', 0)
            ->latest('started_at')
            ->limit(5)
            ->get();

        foreach ($runsWithErrors as $run) {
            $runErrors = $run->getResourceErrors();
            foreach ($runErrors as $resource => $errors) {
                foreach ($errors as $error) {
                    $recentErrors[] = [
                        'run_id' => $run->id,
                        'resource' => $resource,
                        'message' => $error['message'],
                        'timestamp' => $error['timestamp'],
                    ];
                }
            }
        }
        // Limit to 10 most recent errors
        $recentErrors = array_slice($recentErrors, 0, 10);

        return [
            'connection' => [
                'status' => $this->appfolioClient->getStatus(),
                'last_success_at' => $this->appfolioClient->getLastSuccessAt(),
            ],
            'lastRun' => $lastRun ? [
                'id' => $lastRun->id,
                'status' => $lastRun->status,
                'mode' => $lastRun->mode,
                'started_at' => $lastRun->started_at?->toIso8601String(),
                'ended_at' => $lastRun->ended_at?->toIso8601String(),
                'duration' => $lastRun->duration,
                'resources_synced' => $lastRun->resources_synced,
                'errors_count' => $lastRun->errors_count,
            ] : null,
            'lastSuccessAt' => $lastSuccess?->started_at?->toIso8601String(),
            'period' => [
                'days' => $days,
                'total_runs' => $totalRuns,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'success_rate' => $successRate,
            ],
            'chartData' => array_values($chartData),
            'resourceTotals' => $resourceTotals,
            'recentErrors' => $recentErrors,
        ];
    }
}
