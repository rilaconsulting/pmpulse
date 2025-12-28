<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TriggerSyncRequest;
use App\Jobs\SyncAppfolioResourceJob;
use App\Models\AppfolioConnection;
use App\Models\SyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
