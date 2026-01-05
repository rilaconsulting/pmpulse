<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleSsoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

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

    // Google SSO routes
    Route::get('auth/google', [GoogleSsoController::class, 'redirect'])
        ->name('auth.google');
    Route::get('auth/google/callback', [GoogleSsoController::class, 'callback'])
        ->name('auth.google.callback');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Properties
    Route::get('/properties', [PropertyController::class, 'index'])
        ->name('properties.index');
    Route::get('/properties/search', [PropertyController::class, 'search'])
        ->name('properties.search')
        ->middleware('throttle:60,1');
    Route::get('/properties/{property}', [PropertyController::class, 'show'])
        ->name('properties.show');

    // Admin (consolidated with tabs)
    Route::prefix('admin')->name('admin.')->group(function () {
        // Redirect /admin to /admin/users
        Route::redirect('/', '/admin/users');

        // Users management
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::get('/users/create', [AdminController::class, 'usersCreate'])->name('users.create');
        Route::post('/users', [AdminController::class, 'usersStore'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminController::class, 'usersEdit'])->name('users.edit');
        Route::patch('/users/{user}', [AdminController::class, 'usersUpdate'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'usersDestroy'])->name('users.destroy');

        // Integrations (AppFolio)
        Route::get('/integrations', [AdminController::class, 'integrations'])->name('integrations');
        Route::post('/integrations/connection', [AdminController::class, 'saveConnection'])->name('integrations.connection');
        Route::post('/integrations/sync', [AdminController::class, 'triggerSync'])->name('integrations.sync');
        Route::post('/integrations/sync-configuration', [AdminController::class, 'saveSyncConfiguration'])->name('integrations.sync-configuration');

        // Authentication (Google SSO)
        Route::get('/authentication', [AdminController::class, 'authentication'])->name('authentication');
        Route::post('/authentication', [AdminController::class, 'saveAuthentication'])->name('authentication.save');

        // Settings
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    });

    // Profile (all authenticated users)
    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password');
});
