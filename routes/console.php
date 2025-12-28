<?php

use App\Console\Commands\AnalyticsRefreshCommand;
use App\Console\Commands\AppfolioSyncCommand;
use App\Console\Commands\EvaluateAlertsCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Define scheduled commands for the application. These commands are
| registered with the Laravel scheduler.
|
*/

// Full sync runs daily at the configured time
Schedule::command(AppfolioSyncCommand::class, ['--mode=full'])
    ->dailyAt(config('appfolio.full_sync_time', '02:00'))
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sync.log'));

// Incremental sync runs at the configured interval (default: every 15 minutes)
Schedule::command(AppfolioSyncCommand::class, ['--mode=incremental'])
    ->everyFifteenMinutes()
    ->when(fn () => config('features.incremental_sync', true))
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sync.log'));

// Refresh analytics nightly at 3:00 AM (after full sync completes)
Schedule::command(AnalyticsRefreshCommand::class)
    ->dailyAt('03:00')
    ->when(fn () => config('features.analytics_refresh', true))
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/analytics.log'));

// Evaluate alert rules daily at 8:00 AM
Schedule::command(EvaluateAlertsCommand::class)
    ->dailyAt('08:00')
    ->when(fn () => config('features.notifications', true))
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/alerts.log'));
