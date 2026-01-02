<?php

use App\Console\Commands\AnalyticsRefreshCommand;
use App\Console\Commands\AppfolioSyncCommand;
use App\Console\Commands\EvaluateAlertsCommand;
use App\Services\BusinessHoursService;
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
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/sync.log'));

// Incremental sync with business hours awareness
// Runs every minute but only executes when the interval conditions are met:
// - Business hours (9am-5pm Pacific weekdays): every 15 minutes
// - Off-hours: every 60 minutes
Schedule::command(AppfolioSyncCommand::class, ['--mode=incremental'])
    ->everyMinute()
    ->when(function () {
        if (! config('features.incremental_sync', true)) {
            return false;
        }

        $businessHours = app(BusinessHoursService::class);

        return $businessHours->shouldSyncNow();
    })
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/sync.log'));

// Refresh analytics nightly at 3:00 AM (after full sync completes)
Schedule::command(AnalyticsRefreshCommand::class)
    ->dailyAt('03:00')
    ->when(fn () => config('features.analytics_refresh', true))
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/analytics.log'));

// Evaluate alert rules daily at 8:00 AM
Schedule::command(EvaluateAlertsCommand::class)
    ->dailyAt('08:00')
    ->when(fn () => config('features.notifications', true))
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/alerts.log'));
