<?php

use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SyncApiController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VendorApiController;
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
    Route::get('/sync/health', [SyncApiController::class, 'health'])
        ->name('api.sync.health');
    Route::get('/sync/history', [SyncApiController::class, 'history'])
        ->name('api.sync.history');
    Route::post('/sync/trigger', [SyncApiController::class, 'trigger'])
        ->name('api.sync.trigger');

    // Sync failure alerts
    Route::get('/sync/alerts', [SyncApiController::class, 'alerts'])
        ->name('api.sync.alerts');
    Route::post('/sync/alerts/{alert}/acknowledge', [SyncApiController::class, 'acknowledgeAlert'])
        ->name('api.sync.alerts.acknowledge');

    // User management (admin only)
    Route::get('/users/roles', [UserController::class, 'roles'])
        ->name('api.users.roles');
    Route::apiResource('users', UserController::class);

});

// Vendor deduplication API (uses web session auth for same-origin requests)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/vendors/potential-duplicates', [VendorApiController::class, 'potentialDuplicates'])
        ->name('api.vendors.potential-duplicates');
    Route::post('/vendors/{vendor}/mark-duplicate', [VendorApiController::class, 'markDuplicate'])
        ->name('api.vendors.mark-duplicate');
    Route::post('/vendors/{vendor}/mark-canonical', [VendorApiController::class, 'markCanonical'])
        ->name('api.vendors.mark-canonical');
    Route::get('/vendors/{vendor}/duplicates', [VendorApiController::class, 'duplicates'])
        ->name('api.vendors.duplicates');
});
