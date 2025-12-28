<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect root to dashboard
Route::redirect('/', '/dashboard');

// Guest routes with rate limiting for auth endpoints
Route::middleware(['guest', 'throttle:5,1'])->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Admin
    Route::get('/admin', [AdminController::class, 'index'])
        ->name('admin');
    Route::post('/admin/connection', [AdminController::class, 'saveConnection'])
        ->name('admin.connection.save');
    Route::post('/admin/sync', [AdminController::class, 'triggerSync'])
        ->name('admin.sync');
});
