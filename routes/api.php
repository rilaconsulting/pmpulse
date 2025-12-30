<?php

use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SyncApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check - no auth required
Route::get('/health', [HealthController::class, 'check'])
    ->name('api.health');

// Version info - no auth required
Route::get('/version', [HealthController::class, 'version'])
    ->name('api.version');

// Authenticated API routes
Route::middleware('auth:sanctum')->group(function () {
    // Dashboard stats
    Route::get('/dashboard/stats', [DashboardApiController::class, 'stats'])
        ->name('api.dashboard.stats');

    // Sync operations
    Route::get('/sync/history', [SyncApiController::class, 'history'])
        ->name('api.sync.history');
    Route::post('/sync/trigger', [SyncApiController::class, 'trigger'])
        ->name('api.sync.trigger');
});
