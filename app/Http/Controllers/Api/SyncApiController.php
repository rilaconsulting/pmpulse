<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TriggerSyncRequest;
use App\Jobs\SyncAppfolioResourceJob;
use App\Models\AppfolioConnection;
use App\Models\SyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncApiController extends Controller
{
    /**
     * Get sync run history.
     */
    public function history(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 20);
        $limit = min(max($limit, 1), 100);

        $runs = SyncRun::query()
            ->latest('started_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'runs' => $runs,
            'total' => SyncRun::count(),
        ]);
    }

    /**
     * Get sync health metrics for dashboard widget.
     */
    public function health(Request $request): JsonResponse
    {
        $days = $request->integer('days', 7);
        $days = min(max($days, 1), 30);

        $connection = AppfolioConnection::query()->first();

        // Get the last run
        $lastRun = SyncRun::query()
            ->latest('started_at')
            ->first();

        // Get last successful run
        $lastSuccess = SyncRun::query()
            ->where('status', 'completed')
            ->latest('started_at')
            ->first();

        // Get sync stats for the period
        $periodStart = now()->subDays($days)->startOfDay();
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

        // Get recent runs for chart (last N days)
        $recentRuns = SyncRun::query()
            ->where('started_at', '>=', $periodStart)
            ->select(['id', 'status', 'mode', 'started_at', 'ended_at', 'resources_synced', 'errors_count', 'metadata'])
            ->orderBy('started_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($run) {
                return [
                    'id' => $run->id,
                    'status' => $run->status,
                    'mode' => $run->mode,
                    'started_at' => $run->started_at?->toIso8601String(),
                    'ended_at' => $run->ended_at?->toIso8601String(),
                    'duration' => $run->duration,
                    'resources_synced' => $run->resources_synced,
                    'errors_count' => $run->errors_count,
                    'resource_metrics' => $run->getResourceMetrics(),
                ];
            });

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

        // Get aggregate resource metrics from recent syncs
        $resourceTotals = [];
        foreach ($recentRuns as $run) {
            foreach ($run['resource_metrics'] as $resource => $metrics) {
                if (! isset($resourceTotals[$resource])) {
                    $resourceTotals[$resource] = [
                        'created' => 0,
                        'updated' => 0,
                        'skipped' => 0,
                        'errors' => 0,
                    ];
                }
                $resourceTotals[$resource]['created'] += $metrics['created'] ?? 0;
                $resourceTotals[$resource]['updated'] += $metrics['updated'] ?? 0;
                $resourceTotals[$resource]['skipped'] += $metrics['skipped'] ?? 0;
                $resourceTotals[$resource]['errors'] += $metrics['errors'] ?? 0;
            }
        }

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

        return response()->json([
            'connection' => [
                'status' => $connection?->status ?? 'not_configured',
                'last_success_at' => $connection?->last_success_at?->toIso8601String(),
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
                'summary' => $lastRun->getSummary(),
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
            'recentRuns' => $recentRuns,
            'recentErrors' => $recentErrors,
        ]);
    }

    /**
     * Trigger a manual sync via API.
     */
    public function trigger(TriggerSyncRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $connection = AppfolioConnection::query()->first();

        if (! $connection) {
            return response()->json([
                'error' => 'AppFolio connection not configured',
            ], 422);
        }

        // Create a new sync run
        $syncRun = SyncRun::create([
            'appfolio_connection_id' => $connection->id,
            'mode' => $validated['mode'],
            'status' => 'pending',
            'started_at' => now(),
            'metadata' => [
                'triggered_by' => 'api',
                'user_id' => $request->user()->id,
            ],
        ]);

        // Dispatch the sync job
        SyncAppfolioResourceJob::dispatch($syncRun);

        return response()->json([
            'message' => 'Sync job has been queued',
            'sync_run_id' => $syncRun->id,
        ], 202);
    }
}
