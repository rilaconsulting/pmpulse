<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncRun;
use App\Services\AppfolioClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __construct(
        private readonly AppfolioClient $appfolioClient
    ) {}

    /**
     * Health check endpoint.
     *
     * Returns the status of critical services and recent sync information.
     */
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => $this->getVersion(),
            'checks' => [],
        ];

        // Check database connection
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
            ];
            $health['status'] = 'unhealthy';
        }

        // Check cache connection
        try {
            Cache::put('health_check', true, 10);
            Cache::forget('health_check');
            $health['checks']['cache'] = [
                'status' => 'healthy',
                'message' => 'Cache connection successful',
            ];
        } catch (\Exception $e) {
            $health['checks']['cache'] = [
                'status' => 'unhealthy',
                'message' => 'Cache connection failed',
            ];
            $health['status'] = 'unhealthy';
        }

        // Check AppFolio connection status
        if ($this->appfolioClient->isConfigured()) {
            $status = $this->appfolioClient->getStatus();
            $health['checks']['appfolio'] = [
                'status' => $status === 'connected' ? 'healthy' : 'warning',
                'connection_status' => $status,
                'last_success_at' => $this->appfolioClient->getLastSuccessAt(),
            ];
        } else {
            $health['checks']['appfolio'] = [
                'status' => 'warning',
                'message' => 'AppFolio connection not configured',
            ];
        }

        // Check last sync status
        $lastSync = SyncRun::query()
            ->latest('started_at')
            ->first();

        if ($lastSync) {
            $health['checks']['last_sync'] = [
                'status' => $lastSync->status === 'completed' ? 'healthy' : ($lastSync->status === 'failed' ? 'unhealthy' : 'warning'),
                'sync_status' => $lastSync->status,
                'started_at' => $lastSync->started_at->toIso8601String(),
                'ended_at' => $lastSync->ended_at?->toIso8601String(),
            ];

            // If last sync failed, mark as warning
            if ($lastSync->status === 'failed') {
                $health['status'] = $health['status'] === 'healthy' ? 'warning' : $health['status'];
            }
        }

        $statusCode = match ($health['status']) {
            'healthy' => 200,
            'warning' => 200,
            'unhealthy' => 503,
            default => 500,
        };

        return response()->json($health, $statusCode);
    }

    /**
     * Get the application version from VERSION file.
     */
    private function getVersion(): string
    {
        $versionFile = base_path('VERSION');

        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        return 'unknown';
    }

    /**
     * Version endpoint - returns just the version info.
     */
    public function version(): JsonResponse
    {
        return response()->json([
            'version' => $this->getVersion(),
            'app' => config('app.name'),
            'environment' => config('app.env'),
        ]);
    }
}
