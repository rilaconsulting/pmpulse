<?php

use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\AdjustmentReportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleSsoController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\UtilityAccountController;
use App\Http\Controllers\UtilityDashboardController;
use App\Http\Controllers\VendorController;
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
    Route::post('/properties/{property}/flags', [PropertyController::class, 'storeFlag'])
        ->name('properties.flags.store');
    Route::delete('/properties/{property}/flags/{flag}', [PropertyController::class, 'destroyFlag'])
        ->name('properties.flags.destroy');

    // Utilities Dashboard
    Route::get('/utilities', [UtilityDashboardController::class, 'index'])
        ->name('utilities.index');
    Route::get('/utilities/property/{property}', [UtilityDashboardController::class, 'show'])
        ->name('utilities.show');

    // Vendors
    Route::get('/vendors', [VendorController::class, 'index'])
        ->name('vendors.index');
    Route::get('/vendors/compliance', [VendorController::class, 'compliance'])
        ->name('vendors.compliance');
    Route::get('/vendors/compare', [VendorController::class, 'compare'])
        ->name('vendors.compare');
    Route::get('/vendors/deduplication', [VendorController::class, 'deduplication'])
        ->name('vendors.deduplication');
    Route::get('/vendors/{vendor}', [VendorController::class, 'show'])
        ->name('vendors.show');

    // Property Adjustments
    Route::post('/properties/{property}/adjustments', [AdjustmentController::class, 'store'])
        ->name('properties.adjustments.store');
    Route::patch('/properties/{property}/adjustments/{adjustment}', [AdjustmentController::class, 'update'])
        ->name('properties.adjustments.update');
    Route::post('/properties/{property}/adjustments/{adjustment}/end', [AdjustmentController::class, 'end'])
        ->name('properties.adjustments.end');
    Route::delete('/properties/{property}/adjustments/{adjustment}', [AdjustmentController::class, 'destroy'])
        ->name('properties.adjustments.destroy');

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

        // Integrations (AppFolio, Google Maps, Google SSO)
        Route::get('/integrations', [AdminController::class, 'integrations'])->name('integrations');
        Route::post('/integrations/connection', [AdminController::class, 'saveConnection'])->name('integrations.connection');
        Route::post('/integrations/google-maps', [AdminController::class, 'saveGoogleMapsSettings'])->name('integrations.google-maps');
        Route::post('/integrations/google-sso', [AdminController::class, 'saveGoogleSso'])->name('integrations.google-sso');

        // Sync Utilities
        Route::get('/sync', [AdminController::class, 'sync'])->name('sync');
        Route::post('/sync/trigger', [AdminController::class, 'triggerSyncWithOptions'])->name('sync.trigger');
        Route::post('/sync/configuration', [AdminController::class, 'saveSyncConfiguration'])->name('sync.configuration');
        Route::post('/sync/reset-utility-expenses', [AdminController::class, 'resetUtilityExpenses'])->name('sync.reset-utility');

        // Settings (Feature Flags)
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');

        // Adjustments Report
        Route::get('/adjustments', [AdjustmentReportController::class, 'index'])->name('adjustments.index');
        Route::get('/adjustments/export', [AdjustmentReportController::class, 'export'])->name('adjustments.export');

        // Utility Accounts
        Route::get('/utility-accounts', [UtilityAccountController::class, 'index'])->name('utility-accounts.index');
        Route::post('/utility-accounts', [UtilityAccountController::class, 'store'])->name('utility-accounts.store');
        Route::patch('/utility-accounts/{utilityAccount}', [UtilityAccountController::class, 'update'])->name('utility-accounts.update');
        Route::delete('/utility-accounts/{utilityAccount}', [UtilityAccountController::class, 'destroy'])->name('utility-accounts.destroy');

        // Utility Types
        Route::get('/utility-types', [UtilityAccountController::class, 'types'])->name('utility-types.index');
        Route::post('/utility-types', [UtilityAccountController::class, 'storeType'])->name('utility-types.store');
        Route::patch('/utility-types/{key}', [UtilityAccountController::class, 'updateType'])->name('utility-types.update');
        Route::delete('/utility-types/{key}', [UtilityAccountController::class, 'destroyType'])->name('utility-types.destroy');
        Route::post('/utility-types/reset', [UtilityAccountController::class, 'resetTypes'])->name('utility-types.reset');
    });

    // Profile (all authenticated users)
    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password');

    // Changelog
    Route::get('/changelog', [ChangelogController::class, 'index'])
        ->name('changelog');
});
