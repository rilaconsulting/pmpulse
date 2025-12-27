<?php

/**
 * Feature Flags Configuration
 *
 * Default feature flag values. These can be overridden by database
 * settings in the feature_flags table.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Incremental Sync
    |--------------------------------------------------------------------------
    |
    | Enable or disable incremental sync jobs that run every N minutes.
    | When disabled, only full syncs will occur.
    |
    */

    'incremental_sync' => env('FEATURE_INCREMENTAL_SYNC', true),

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Enable or disable email notifications for alerts.
    | When disabled, alerts will still be evaluated but not sent.
    |
    */

    'notifications' => env('FEATURE_NOTIFICATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Analytics Refresh
    |--------------------------------------------------------------------------
    |
    | Enable or disable automatic analytics refresh.
    | When disabled, analytics tables won't be updated automatically.
    |
    */

    'analytics_refresh' => env('FEATURE_ANALYTICS_REFRESH', true),

];
